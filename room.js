let total = 0;

function addRoomToSummary(roomName, price) {
  const roomList = document.getElementById("room-list");
  const li = document.createElement("li");

  li.innerHTML = `
    <span>${roomName} - ₱${price}</span>
    <button class="remove-room">&times;</button>
  `;

  // Add event listener for removing room
  li.querySelector(".remove-room").addEventListener("click", () => {
    li.remove();
    updateTotal();
  });

  roomList.appendChild(li);
  updateTotal();
}

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
    title: "Room 2 ",
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
    title: "Room 3 ",
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

// Show modal
function showDetails(roomId) {
  const modal = document.getElementById("roomModal");
  const modalTitle = document.getElementById("modalTitle");
  const modalDescription = document.getElementById("modalDescription");

  // Load data
  modalTitle.textContent = roomDetails[roomId].title;
  modalDescription.textContent = roomDetails[roomId].description;

  // Show modal
  modal.style.display = "block";
}

// Close modal
function closeModal() {
  document.getElementById("roomModal").style.display = "none";
}

// Close modal if user clicks outside
window.onclick = function(event) {
  const modal = document.getElementById("roomModal");
  if (event.target === modal) {
    modal.style.display = "none";
  }
}
