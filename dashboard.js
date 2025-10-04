// === NAVIGATION TABS ===
// Grab all navigation buttons and page sections
const navButtons = document.querySelectorAll(".nav-btn");
const sections = document.querySelectorAll(".section");

// Grab modal elements for calendar
const calendarModal = document.getElementById("calendarModal");
const closeBtn = document.querySelector(".close-btn");
const generateBtn = document.getElementById("generateBtn");
const monthSelect = document.getElementById("month");
const yearInput = document.getElementById("year");

// Loop through each nav button (Revenue, History, Calendar, Actions)
navButtons.forEach(btn => {
  btn.addEventListener("click", () => {
    // Remove "active" class from all buttons
    navButtons.forEach(b => b.classList.remove("active"));

    // Hide all sections
    sections.forEach(s => s.classList.add("hidden"));

    // Activate the clicked button
    btn.classList.add("active");

    // Show the related section
    const sectionId = btn.getAttribute("data-section");
    document.getElementById(sectionId).classList.remove("hidden");

    // If "Calendar" is clicked, open the modal to select month & year
    if (sectionId === "calendar") {
      calendarModal.style.display = "block";
    }
  });
});

// === MODAL CONTROLS ===
// Close modal when "X" button is clicked
closeBtn.addEventListener("click", () => {
  calendarModal.style.display = "none";
});

// Close modal when clicking outside of it
window.addEventListener("click", (e) => {
  if (e.target == calendarModal) {
    calendarModal.style.display = "none";
  }
});

// === SAMPLE BOOKING DATA ===
// Each booking has a day and customer name
const bookings = [
  { day: 5, customer: "John Doe" },
  { day: 12, customer: "Maria Cruz" },
  { day: 18, customer: "Paul Santos" },
  { day: 25, customer: "Anna Reyes" }
];

// === GENERATE CALENDAR ===
// Triggered when "Generate Calendar" button is clicked
generateBtn.addEventListener("click", () => {
  const month = parseInt(monthSelect.value); // Selected month
  const year = parseInt(yearInput.value);    // Selected year
  generateCalendar(month, year);             // Build calendar grid
  calendarModal.style.display = "none";      // Close modal
});

// === CALENDAR GENERATOR FUNCTION ===
function generateCalendar(month, year) {
  const calendar = document.getElementById("calendarGrid"); // Calendar display container
  const bookingList = document.getElementById("bookingList"); // Sidebar booking list

  // Reset previous content
  calendar.innerHTML = "";
  bookingList.innerHTML = "";

  // Month names for header
  const monthNames = [
    "January","February","March","April","May","June",
    "July","August","September","October","November","December"
  ];

  // Title for selected month and year
  const title = document.createElement("h3");
  title.textContent = `${monthNames[month]} ${year}`;
  title.style.textAlign = "center";
  title.style.color = "#3D1203";
  calendar.appendChild(title);

  // Find number of days in the selected month
  const daysInMonth = new Date(year, month + 1, 0).getDate();

  // Create grid layout (7 columns for 7 days)
  const grid = document.createElement("div");
  grid.style.display = "grid";
  grid.style.gridTemplateColumns = "repeat(7, 1fr)";
  grid.style.gap = "5px";

  // Loop through days
  for (let i = 1; i <= daysInMonth; i++) {
    const day = document.createElement("div");
    day.textContent = i; // Show day number
    day.style.padding = "10px";
    day.style.textAlign = "center";
    day.style.borderRadius = "8px";

    // Check if this day has a booking
    const booking = bookings.find(b => b.day === i);

    if (booking) {
      day.classList.add("booked"); // Highlight day as booked

      // Add booking details to the sidebar list
      const li = document.createElement("li");
      li.textContent = `Day ${i}: ${booking.customer}`;
      bookingList.appendChild(li);
    }

    grid.appendChild(day); // Add day cell to grid
  }

  // Attach grid to calendar container
  calendar.appendChild(grid);
}

// === NOTIFICATION BAR UPDATE ===
// After 5 seconds, show a conflict warning
setTimeout(() => {
  const notif = document.getElementById("notificationBar");
  notif.innerHTML = `<i class="fa-solid fa-exclamation-circle"></i> Conflict detected: Room 102 double-booked!`;
  notif.style.background = "#E48523"; // Change background to orange alert
}, 5000);
