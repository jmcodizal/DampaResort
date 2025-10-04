let total = 0;

// ==========================
// Add a room to Booking Summary
// ==========================
function addRoom(roomName, price) {
  const roomList = document.getElementById("room-list");
  const li = document.createElement("li");

  li.innerHTML = `
    <span>${roomName} - ₱${price}</span>
    <button class="remove-room">&times;</button>
  `;

  // Remove room when "x" is clicked
  li.querySelector(".remove-room").addEventListener("click", () => {
    li.remove();
    updateTotal();
  });

  roomList.appendChild(li);
  updateTotal();
}

// ==========================
// Update total price
// ==========================
function updateTotal() {
  const roomList = document.getElementById("room-list").children;
  let total = 0;

  for (let item of roomList) {
    const text = item.querySelector("span").innerText;
    const price = parseInt(text.split("₱")[1]);
    total += price;
  }

  document.getElementById("total").innerText = total;
}

// ==========================
// Room Details (for modal)
// ==========================
const roomDetails = {
  room1: {
    title: "Room 1",
    description: `
      With Air-conditioning
      Capacity: 15 pax per room (sleeping capacity)
      Rate: ₱7,000 each
      • Each has its own CR (with tiles), sink & faucet (unli water, pressure tank)
      • Free open cottage extension (long table, chairs, 1 wall fan)
      • Free use of single burner gas stove & stainless steel grill
    `
  },
  room2: {
    title: "Room 2",
    description: `
      With Air-conditioning
      Capacity: 15 pax per room (sleeping capacity)
      Rate: ₱7,000 each
      • Each has its own CR (with tiles), sink & faucet (unli water, pressure tank)
      • Free open cottage extension (long table, chairs, 1 wall fan)
      • Free use of single burner gas stove & stainless steel grill
    `
  },
  room3: { 
    title: "Room 3",
    description: `
      With Air-conditioning
      Capacity: 15 pax per room (sleeping capacity)
      Rate: ₱7,000 each
      • Each has its own CR (with tiles), sink & faucet (unli water, pressure tank)
      • Free open cottage extension (long table, chairs, 1 wall fan)
      • Free use of single burner gas stove & stainless steel grill
    `
  },
  room4: {
    title: "Couple Room",
    description: `
      For 2 to 7 pax sleeping capacity
      Rate: ₱2,500
      • Own CR, sink & faucet
      • Free open cottage extension with long table, chairs, wall fan
      • Free use of single burner gas stove & stainless steel grill
    `
  },
  room5: {
    title: "Big Room",
    description: `
      For 20 pax sleeping capacity
      Rate: ₱7,000
      • Own CR, sink & faucet
      • Free open cottage extension with long table, chairs, wall fan
      • Free use of single burner gas stove & stainless steel grill
    `
  }
};

// ==========================
// Show modal with room details
// ==========================
function showDetails(roomId) {
  const modal = document.getElementById("roomModal");
  const modalTitle = document.getElementById("modalTitle");
  const modalDescription = document.getElementById("modalDescription");

  modalTitle.textContent = roomDetails[roomId].title;
  modalDescription.textContent = roomDetails[roomId].description;

  modal.style.display = "block";
}

// Close modal
function closeModal() {
  document.getElementById("roomModal").style.display = "none";
}

// Close modal when clicking outside
window.onclick = function(event) {
  const modal = document.getElementById("roomModal");
  if (event.target === modal) {
    modal.style.display = "none";
  }
};

// ==========================
// Filter rooms by date
// ==========================
function filterRooms() {
  const selectedDate = document.getElementById("filterDate").value;
  const statuses = document.querySelectorAll(".room-status");

  // Reset statuses first
  statuses.forEach(s => {
    s.innerText = "Available";
    s.style.backgroundColor = "#2ecc71";
  });

  if (!selectedDate) return;

  // Example reserved data (you need to define reservedRooms globally)
  const reserved = reservedRooms[selectedDate] || [];

  for (let i = 1; i <= 5; i++) {
    const roomId = "room" + i;
    const statusEl = document.getElementById("status-" + roomId);
    if (reserved.includes(roomId)) {
      statusEl.innerText = "Reserved";
      statusEl.style.backgroundColor = "#e67e22";
    }
  }
}
