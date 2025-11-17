<?php
session_start();

// --- Database Connection ---
$servername = "localhost";
$username = "root";
$password = "";
$database = "dampa_booking";

$conn = new mysqli($servername, $username, $password, $database);
if ($conn->connect_error) die("Database connection failed: " . $conn->connect_error);
$conn->set_charset("utf8mb4"); 

$custom_order = ['Room 1','Room 2','Room 3','Couple Room','Big Room'];
$order_sql = "FIELD(room_name,'".implode("','",$custom_order)."')";
$rooms = [];
$q = $conn->query("SELECT * FROM rooms ORDER BY $order_sql ASC");
while ($row = $q->fetch_assoc()) $rooms[] = $row;

function get_room_ratings($conn, $roomName) {
    $stmt = $conn->prepare("
        SELECT r.rating, r.review_text, r.review_date,
               CONCAT(c.first_name,' ',c.last_name) AS full_name
        FROM ratings r
        JOIN bookings b ON r.booking_id = b.booking_id
        JOIN customer c ON r.customer_id = c.customer_id
        WHERE JSON_SEARCH(b.rooms,'one',?) IS NOT NULL
        ORDER BY r.created_at DESC
    ");
    $stmt->bind_param("s", $roomName);
    $stmt->execute();
    $res = $stmt->get_result();
    $ratings = [];
    while ($row = $res->fetch_assoc()) $ratings[] = $row;
    $stmt->close();
    return $ratings;
}

$roomRatings = []; 
$roomStats = [];   
$maxBookings = 0;  

foreach ($rooms as $room) {
    $rId = $room['id'];
    $ratings = get_room_ratings($conn, $room['room_name']);
    $roomRatings[$rId] = $ratings;

    $total = count($ratings);
    $avg = $total ? array_sum(array_column($ratings,'rating'))/$total : 0;

    $stmt = $conn->prepare("SELECT COUNT(*) AS cnt FROM bookings WHERE JSON_SEARCH(rooms,'one',?) IS NOT NULL");
    $stmt->bind_param("s", $room['room_name']);
    $stmt->execute();
    $cntRow = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    $bookings_count = intval($cntRow['cnt'] ?? 0);

    $roomStats[$rId] = ['avg'=>round($avg,2),'total'=>$total,'bookings'=>$bookings_count];
    if($bookings_count > $maxBookings) $maxBookings = $bookings_count; 
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Explore Rooms — DAMPA</title>


<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

<!-- --- Styles --- -->
<style>
:root{
    --bg:#f6f2ed;
    --card:#ffffff;
    --accent:#7a2e1c;
    --muted:#6b4a3a;
    --pill:#e9d8cf;
    --radius:12px;
}
body{
    margin:0;
    font-family:"Poppins",sans-serif;
    background:var(--bg);
    color:var(--muted);
    padding:20px;
}
.header{
    display:flex;
    align-items:center;
    gap:16px;
    margin-bottom:18px;
}
.brand{
    display:flex;
    align-items:center;
    gap:8px;
}
.brand img{
    height:44px;
    border-radius:8px;
}
h1{
    margin:0;
    font-size:1.4rem;
    color:var(--accent);
}
.controls{
    display:flex;
    gap:10px;
    flex-wrap:wrap;
    margin-bottom:18px;
}
.search{
    flex:1 1 320px;
    display:flex;
    align-items:center;
    gap:8px;
    background:var(--card);
    padding:8px 10px;
    border-radius:10px;
    border:1px solid rgba(0,0,0,0.06);
}
.search input{
    border:0;
    outline:0;
    flex:1;
    font-size:0.95rem;
}
.selects{
    display:flex;
    gap:8px;
    align-items:center;
}
.grid{
    display:grid;
    grid-template-columns:repeat(auto-fill,minmax(320px,1fr));
    gap:18px;
}
.room-card{
    background:var(--card);border-radius:var(--radius);
    overflow:hidden;
    box-shadow:0 6px 20px rgba(30,20,18,0.06);
    border:1px solid rgba(0,0,0,0.03);
    display:flex;
    flex-direction:column;
    height:100%;
}
.room-media{
    position:relative;
    height:200px;
    overflow:hidden;
    cursor:pointer;
}
.room-media img{
    width:100%;
    height:100%;
    object-fit:cover;
    transition:transform .45s;
}
.room-media:hover img{
    transform:scale(1.03);
}
.badges{
    position:absolute;
    top:10px;
    left:10px;
    display:flex;
    gap:8px;
}
.badge{
    background:var(--pill);
    color:var(--accent);
    padding:6px 8px;
    border-radius:999px;
    font-weight:600;
    font-size:0.8rem;
}
.room-body{
    flex:1 1 auto;
    padding:14px;
    display:flex;
    flex-direction:column;
}
.title-row{
    display:flex;
    align-items:center;
    justify-content:space-between;
    gap:10px;
}
.room-title{
    font-weight:700;
    color:var(--accent);
}
.price{
    font-weight:800;
    color:var(--accent);
}
.meta{
    font-size:0.9rem;
    color:#7a5a4d;margin-top:6px;
    display:flex;
    gap:12px;
    align-items:center;
    flex-wrap:wrap;
}
.amenities{
    margin-top:8px;
    display:flex;
    gap:8px;
    flex-wrap:wrap;
    font-size:0.9rem;
    color:#6b4a3a;}
.amenity{
    background:#fff3ee;
    padding:6px 8px;
    border-radius:8px;
    font-weight:600;
}
.card-actions{
    margin-top:auto;
    display:flex;
    gap:8px;
    align-items:center;
    padding-top: 10px;
}
.btn{
    padding:8px 12px;
    border-radius:10px;
    border:0;
    cursor:pointer;
    font-weight:700;
}
.btn-primary{
    background:var(--accent);
    color:white;
}
.btn-outline{
    background:transparent;
    border:1px solid rgba(0,0,0,0.08);
    color:var(--muted);
}
.rating{
    display:flex;
    gap:6px;
    align-items:center;
    font-weight:700;
    color:#b86b2b;
}
.modal{
    position:fixed;
    inset:0;
    display:none;
    align-items:center;
    justify-content:center;
    background:rgba(0,0,0,0.6);
    z-index:9999;
    padding:20px;}
.modal .content{
    width:100%;
    max-width:900px;
    background:white;
    border-radius:12px;
    overflow:hidden;
    position:relative;
}
.modal .content img{
    width:100%;
    height:520px;
    object-fit:cover;
    display:block;
}
.modal .close{
    position:absolute;
    right:12px;
    top:12px;
    background:white;
    border-radius:999px;
    padding:6px 8px;
    cursor:pointer;}
@media(max-width:600px){
    .room-media{height:160px}
    .controls{flex-direction:column}
}
.small{
    font-size:0.85rem;
    color:#8a6f62;
}
</style>
</head>
<body>

<!-- --- Header Section --- -->
<div class="header">
    <div class="brand">
        <img src="images/Dampa Logo.png" alt="logo" onerror="this.style.display='none'">
        <div>
            <h1>Explore Rooms</h1>
            <div class="small">Check availability, ratings & amenities</div>
        </div>
    </div>
</div>

<!-- --- Search & Sort Controls --- -->
<div class="controls">
    <div class="search"><i class="fa fa-search"></i>
        <input type="text" id="searchInput" placeholder="Search by room name or amenity...">
    </div>
    <div class="selects">
        <select id="sortSelect">
            <option value="default">Sort: Default</option>
            <option value="price_asc">Price: Low → High</option>
            <option value="price_desc">Price: High → Low</option>
            <option value="rating_desc">Rating: High → Low</option>
        </select>
    </div>
</div>

<!-- --- Rooms Grid --- -->
<div id="roomsGrid" class="grid">
<?php 
$placeholder = 'https://via.placeholder.com/800x500?text=Room+Image';

foreach ($rooms as $room):
    $rId = $room['id'];
    $stats = $roomStats[$rId];
    $ratings = $roomRatings[$rId];

    $filename = $room['room_image']; 
    
    $imagePath = 'images/' . $filename;
    $absPath = __DIR__ . '/' . $imagePath;


    if (!empty($filename) && file_exists($absPath)) {
        $image = $imagePath;
    } else {
        $image = $placeholder;
    }

    $amenities = !empty($room['amenities']) ? preg_split("/[\n,]+/", $room['amenities']) : [];

    $isTopRated = ($stats['avg'] >= 4.5 && $stats['total'] >= 3); // Top Rated badge
    $isPopular = ($maxBookings > 0 && $stats['bookings'] >= max(1, intval($maxBookings * 0.6))); // Most Booked badge
?>
<div class="room-card" 
     data-price="<?= $room['rate'] ?>" 
     data-rating="<?= $stats['avg'] ?>" 
     data-name="<?= strtolower($room['room_name']) ?>" 
     data-amenities="<?= strtolower(implode(',',$amenities)) ?>">

    <!-- --- Room Image & Badges --- -->
    <div class="room-media" onclick="openModal('<?= htmlspecialchars($image) ?>')">
        <img src="<?= htmlspecialchars($image) ?>" alt="<?= htmlspecialchars($room['room_name']) ?>" onerror="this.src='<?= $placeholder ?>'">
        <div class="badges">
            <?php if($isTopRated): ?><div class="badge">Top Rated</div><?php endif; ?>
            <?php if($isPopular): ?><div class="badge">Most Booked</div><?php endif; ?>
        </div>
    </div>

    <!-- --- Room Details --- -->
    <div class="room-body">
        <div class="title-row">
            <div class="room-title"><?= htmlspecialchars($room['room_name']) ?></div>
            <div class="rating"><?= $stats['total'] ? number_format($stats['avg'],1).' ★' : 'No ratings' ?></div>
            <div class="price">₱<?= number_format($room['rate'],2) ?></div>
        </div>

        <!-- Amenities -->
        <?php if($amenities): ?>
        <div class="amenities"><?php foreach($amenities as $am): ?><div class="amenity"><?= htmlspecialchars($am) ?></div><?php endforeach; ?></div>
        <?php endif; ?>

        <!-- Action Buttons -->
        <div class="card-actions">
            <button class="btn btn-primary" onclick="window.location.href='book_room.php?room_id=<?= $rId ?>'">Book Now</button>
            <button class="btn btn-outline" onclick="openRatingsModal(<?= $rId ?>)">Show Ratings & Reviews</button>
        </div>

        <!-- Hidden Ratings Section -->
        <div class="rating-section" id="ratings-<?= $rId ?>" style="display:none;">
            <?php if(!empty($ratings)):
                $total=count($ratings);
                $avg=array_sum(array_column($ratings,'rating'))/$total;
            ?>
            <p><strong>Average Rating:</strong> <?= number_format($avg,1) ?> ★ (<?= $total ?> reviews)</p>
            <ul>
            <?php foreach($ratings as $r): ?>
                <li><?= str_repeat('★',(int)$r['rating']) ?> by <strong><?= htmlspecialchars($r['full_name']) ?></strong>
                <?php if(!empty($r['review_text'])): ?> — “<?= htmlspecialchars($r['review_text']) ?>”<?php endif; ?></li>
            <?php endforeach; ?>
            </ul>
            <?php else: ?><p>No ratings yet.</p><?php endif; ?>
        </div>
    </div>
</div>
<?php endforeach; ?>
</div>

<!-- --- Image Modal --- -->
<div id="modal" class="modal">
    <div class="content">
        <button class="close" onclick="closeModal()"><i class="fa fa-times"></i></button>
        <img id="modalImage" src="" alt="Room image">
    </div>
</div>

<!-- --- Ratings Modal --- -->
<div id="ratingsModal" class="modal">
    <div class="content">
        <button class="close" onclick="closeRatingsModal()"><i class="fa fa-times"></i></button>
        <div id="ratingsContent"></div>
    </div>
</div>

<!-- --- Scripts --- -->
<script>
function openModal(src){document.getElementById('modalImage').src=src;document.getElementById('modal').style.display='flex';}
function closeModal(){document.getElementById('modal').style.display='none';}

function openRatingsModal(id){document.getElementById('ratingsContent').innerHTML=document.getElementById('ratings-'+id).innerHTML;document.getElementById('ratingsModal').style.display='flex';}
function closeRatingsModal(){document.getElementById('ratingsModal').style.display='none';}

const roomsGrid = document.getElementById('roomsGrid');
const searchInput = document.getElementById('searchInput');
const sortSelect = document.getElementById('sortSelect');

const allCards = Array.from(roomsGrid.querySelectorAll('.room-card'));

// Apply filters & sort
function applyFilters(){
    let q = searchInput.value.trim().toLowerCase();
    let sort = sortSelect.value;

    let filtered = allCards.filter(c=>{
        return !q || c.dataset.name.includes(q) || c.dataset.amenities.includes(q);
    });

    if(sort==='default') {
        const order=['room 1','room 2','room 3','couple room','big room'];
        filtered.sort((a,b)=>order.indexOf(a.dataset.name)-order.indexOf(b.dataset.name));
    } else if(sort==='price_asc') filtered.sort((a,b)=>parseFloat(a.dataset.price)-parseFloat(b.dataset.price));
    else if(sort==='price_desc') filtered.sort((a,b)=>parseFloat(b.dataset.price)-parseFloat(a.dataset.price));
    else if(sort==='rating_desc') filtered.sort((a,b)=>parseFloat(b.dataset.rating)-parseFloat(a.dataset.rating));

    roomsGrid.innerHTML='';
    filtered.forEach(c=>roomsGrid.appendChild(c));
}

searchInput.addEventListener('input',applyFilters);
sortSelect.addEventListener('change',applyFilters);
applyFilters();

document.getElementById('modal').addEventListener('click',e=>{if(e.target===this)closeModal();});
document.getElementById('ratingsModal').addEventListener('click',e=>{if(e.target===this)closeRatingsModal();});
</script>
</body>
</html>
