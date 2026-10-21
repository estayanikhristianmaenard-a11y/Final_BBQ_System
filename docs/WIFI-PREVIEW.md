# Same-Wi-Fi preview

Current preview address: `http://192.168.1.71:8081/`.

The dedicated PHP demo router is running on the PC's Wi-Fi address on port 8081.
The ordinary loopback preview on port 8080 remains available. MySQL and the PC
must remain running, and the phone must use the same Wi-Fi network.

The menu, cart, and stylesheet respond with HTTP 200 through the Wi-Fi address.
Configuration and SQL snapshot requests are blocked with HTTP 403. The restored
banner and six product descriptions were reviewed at 390px with no horizontal
overflow. Search and category filters remain removed.

Windows denied creation of the inbound firewall rule because the current process
does not have administrator privileges. A local, untracked helper is available at
`.tmp/ALLOW-WIFI-PREVIEW.bat`: right-click it in File Explorer and choose **Run as
administrator** if the phone cannot connect. The rule permits only PHP on Wi-Fi
TCP 8081 at 192.168.1.71, from 192.168.1.0/24; it does not disable the firewall.

Phone connectivity has not yet been confirmed from a separate device. Guest
Wi-Fi or router client isolation can prevent devices from reaching each other.

To restart this preview from the project root in PowerShell:

```powershell
& C:/xampp/php/php.exe -S 192.168.1.71:8081 -t bulkbites-bbq bulkbites-bbq/tools/demo-router.php
```

Keep that terminal open. Run `ipconfig` to check the Wi-Fi IPv4 address after
changing networks; the URL, server binding, and firewall rule must match it.
This local HTTP preview is for testing; the ngrok HTTPS/payment demo is separate.
