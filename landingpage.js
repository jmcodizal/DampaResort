/* ============================
   Toggle Navigation (Mobile Menu)
=============================== */
function myFunction() {
  // Get the navigation links container
  var x = document.getElementById("myLinks");

  // If the nav links are already visible, hide them
  if (x.style.display === "block") {
    x.style.display = "none";
  } 
  // Otherwise, show them
  else {
    x.style.display = "block";
  }
}


/* ============================
   Search Button Action
=============================== */
document.getElementById("searchBtn").addEventListener("click", function() {
  // For now, show an alert when search is clicked
  alert("Search feature coming soon!");
});
