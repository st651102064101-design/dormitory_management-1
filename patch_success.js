// By hiding the children inside .login-card except the overlay, it shrinks to a nice size!
document.querySelectorAll('.login-card > *:not(.login-success-overlay)').forEach(el => el.style.display = 'none');
