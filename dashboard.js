// === Navigation Tabs ===
const navButtons = document.querySelectorAll(".nav-btn");
const sections = document.querySelectorAll(".section");

const calendarModal = document.getElementById("calendarModal");
const closeBtn = document.querySelector(".close-btn");
const generateBtn = document.getElementById("generateBtn");
const monthSelect = document.getElementById("month");
const yearInput = document.getElementById("year");

navButtons.forEach(btn => {
  btn.addEventListener("click", () => {
    navButtons.forEach(b => b.classList.remove("active"));
    sections.forEach(s => s.classList.add("hidden"));

    btn.classList.add("active");
    const sectionId = btn.getAttribute("data-section");
    document.getElementById(sectionId).classList.remove("hidden");

    if (sectionId === "calendar") {
      calendarModal.style.display = "block";
    }
  });
});

// Close modal
closeBtn.addEventListener("click", () => {
  calendarModal.style.display = "none";
});

// Close when clicking outside
window.addEventListener("click", (e) => {
  if (e.target == calendarModal) {
    calendarModal.style.display = "none";
  }
});

// Example booking data
const bookings = [
  { day: 5, customer: "John Doe" },
  { day: 12, customer: "Maria Cruz" },
  { day: 18, customer: "Paul Santos" },
  { day: 25, customer: "Anna Reyes" }
];

// Generate calendar when button clicked
generateBtn.addEventListener("click", () => {
  const month = parseInt(monthSelect.value);
  const year = parseInt(yearInput.value);
  generateCalendar(month, year);
  calendarModal.style.display = "none";
});

// === Calendar Generator ===
function generateCalendar(month, year) {
  const calendar = document.getElementById("calendarGrid");
  const bookingList = document.getElementById("bookingList");

  calendar.innerHTML = "";
  bookingList.innerHTML = "";

  const monthNames = [
    "January","February","March","April","May","June",
    "July","August","September","October","November","December"
  ];
  const title = document.createElement("h3");
  title.textContent = `${monthNames[month]} ${year}`;
  title.style.textAlign = "center";
  title.style.color = "#3D1203";
  calendar.appendChild(title);

  const daysInMonth = new Date(year, month + 1, 0).getDate();

  const grid = document.createElement("div");
  grid.style.display = "grid";
  grid.style.gridTemplateColumns = "repeat(7, 1fr)";
  grid.style.gap = "5px";

  for (let i = 1; i <= daysInMonth; i++) {
    const day = document.createElement("div");
    day.textContent = i;
    day.style.padding = "10px";
    day.style.textAlign = "center";
    day.style.borderRadius = "8px";

    const booking = bookings.find(b => b.day === i);

    if (booking) {
      day.classList.add("booked");

      const li = document.createElement("li");
      li.textContent = `Day ${i}: ${booking.customer}`;
      bookingList.appendChild(li);
    }

    grid.appendChild(day);
  }

  calendar.appendChild(grid);
}

// === Notification ===
setTimeout(() => {
  const notif = document.getElementById("notificationBar");
  notif.innerHTML = `<i class="fa-solid fa-exclamation-circle"></i> Conflict detected: Room 102 double-booked!`;
  notif.style.background = "#E48523";
}, 5000);
