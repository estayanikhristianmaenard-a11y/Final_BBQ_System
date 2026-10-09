/* Password visibility toggle — works on ANY password field in the project.
   Markup produced by PHP:
     <div class="password-wrap">
       <input type="password" id="login_password" ...>
       <button type="button" class="password-toggle"
               data-toggle-password="login_password" aria-label="Show password">&#128065;</button>
     </div>
*/
document.addEventListener('click', function (event) {
  var button = event.target.closest('[data-toggle-password]');
  if (!button) return;

  var input = document.getElementById(button.getAttribute('data-toggle-password'));
  if (!input) return;

  var willShow = input.type === 'password';
  input.type = willShow ? 'text' : 'password';
  button.innerHTML = willShow ? '&#128065;&#824;' : '&#128065;';
  button.setAttribute('aria-label', willShow ? 'Hide password' : 'Show password');
});
