const togglePassword = document.querySelector("#togglePassword");
const passwordInput = document.querySelector("#password");

togglePassword.addEventListener("click", function () {
  // Toggle input type
  const type = passwordInput.getAttribute("type") === "password" ? "text" : "password";
  passwordInput.setAttribute("type", type);

  // Toggle icon (eye / eye-slash)
  this.classList.toggle("bi-eye-fill");
  this.classList.toggle("bi-eye-slash-fill");
});
