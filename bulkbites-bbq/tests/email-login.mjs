// Local TLS SMTP fixture: no messages or credentials go to an external service.
import tls from 'node:tls';
import http from 'node:http';
import fs from 'node:fs';
import os from 'node:os';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { execFileSync, spawn } from 'node:child_process';

const php = process.argv[2] || 'php';
const xampp = path.dirname(path.dirname(path.resolve(php)));
const directory = fs.mkdtempSync(path.join(os.tmpdir(), 'bbq-email-test-'));
const cert = path.join(directory, 'cert.pem');
const key = path.join(directory, 'key.pem');
const openssl = path.join(xampp, 'apache', 'bin', 'openssl.exe');
const smtp = tls.createServer;
let server, api;
let code = '', rejectDelivery = false, rejectAuthentication = false, messages = 0;
const deliveredMessages = [];
try {
  execFileSync(openssl, ['req', '-x509', '-newkey', 'rsa:2048', '-nodes', '-days', '1',
    '-config', path.join(xampp, 'apache', 'conf', 'openssl.cnf'),
    '-subj', '/CN=localhost', '-addext', 'subjectAltName=DNS:localhost',
    '-keyout', key, '-out', cert], { stdio: 'ignore' });
  server = smtp({ key: fs.readFileSync(key), cert: fs.readFileSync(cert) }, socket => {
    socket.setEncoding('utf8');
    socket.write('220 localhost test SMTP\r\n');
    let buffer = '', data = false, body = '', auth = 0;
    socket.on('error', () => {});
    socket.on('data', chunk => {
      buffer += chunk;
      let newline;
      while ((newline = buffer.indexOf('\r\n')) !== -1) {
        const line = buffer.slice(0, newline);
        buffer = buffer.slice(newline + 2);
        if (data) {
          if (line === '.') {
            data = false;
            if (!rejectDelivery) {
              const match = body.match(/sign-in code is: (\d{6})/);
              if (match) code = match[1];
              messages++;
              deliveredMessages.push(body);
            }
            socket.write(rejectDelivery ? '550 Delivery rejected by fixture\r\n' : '250 Message accepted\r\n');
          } else { body += line + '\n'; }
        } else if (auth) {
          socket.write(auth++ === 1 ? '334 UGFzc3dvcmQ6\r\n'
            : rejectAuthentication ? '535 5.7.8 Username and Password not accepted\r\n' : '235 Authenticated\r\n');
          if (auth === 3) auth = 0;
        } else if (/^EHLO/.test(line)) socket.write('250-localhost\r\n250 AUTH LOGIN\r\n');
        else if (line === 'AUTH LOGIN') { auth = 1; socket.write('334 VXNlcm5hbWU6\r\n'); }
        else if (line === 'DATA') { data = true; body = ''; socket.write('354 Send message\r\n'); }
        else if (line === 'QUIT') { socket.end('221 Goodbye\r\n'); }
        else socket.write('250 OK\r\n');
      }
    });
  });
  await new Promise(resolve => server.listen(0, '127.0.0.1', resolve));
  api = http.createServer((req, res) => {
    const url = new URL(req.url, 'http://localhost');
    if (url.searchParams.has('fail')) rejectDelivery = url.searchParams.get('fail') === '1';
    if (url.searchParams.has('authFail')) rejectAuthentication = url.searchParams.get('authFail') === '1';
    res.end(JSON.stringify({ code, messages: deliveredMessages }));
  });
  await new Promise(resolve => api.listen(0, '127.0.0.1', resolve));
  const runner = spawn(php, ['-d', `openssl.cafile=${cert}`, fileURLToPath(new URL('./email-login.php', import.meta.url))], {
    env: { ...process.env, BB_TEST_SMTP_PORT: String(server.address().port), BB_TEST_HTTP_PORT: String(api.address().port) },
    stdio: 'inherit',
  });
  const status = await new Promise((resolve, reject) => { runner.on('exit', resolve); runner.on('error', reject); });
  if (status !== 0) process.exitCode = status || 1;
  else console.log(`TLS SMTP fixture accepted ${messages} messages; no external emails sent.`);
} finally {
  server?.close();
  api?.close();
  if (path.dirname(path.resolve(directory)) !== path.resolve(os.tmpdir()) || !path.basename(directory).startsWith('bbq-email-test-')) {
    throw new Error('Refusing to remove a directory outside the test temporary folder.');
  }
  fs.rmSync(directory, { recursive: true, force: true });
}
