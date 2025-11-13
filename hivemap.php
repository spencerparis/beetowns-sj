<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Bee Towns - Hive Map</title>

  <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"/>
  <style>
    body, html, #map { height: 100%; margin: 0; padding: 0; }
    .leaflet-container { background: #eef; }
    .hex-dropdown {
      font-size: 14px;
      padding: 4px;
      width: 160px;
    }
    .big-menu {
      margin-top: 8px;
      padding: 6px;
      width: 200px;
      font-size: 15px;
    }
  </style>
</head>
<body>
<div id="map"></div>

<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script src="https://unpkg.com/@turf/turf@6.5.0/turf.min.js"></script>

<script>

// Cornwall bounding box [west, south, east, north]
const cornwallBbox = [-5.75, 49.9, -3.9, 51.1];

// Create the map with a fallback center (Truro)
const map = L.map('map').setView([50.3755, -4.1427], 14);

// Add tiles
L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
  attribution: '&copy; OpenStreetMap contributors'
}).addTo(map);

// Try to locate user
map.locate({ setView: true, maxZoom: 16 });

// Drop marker on user location
map.on("locationfound", (e) => {
  const userMarker = L.marker(e.latlng).addTo(map);
  userMarker.bindPopup("You are here").openPopup();
});

// Handle location error
map.on("locationerror", () => {
  console.warn("Could not get user location.");
});

// Generate hex grid (300m)
const hexGrid = turf.hexGrid(cornwallBbox, 0.3, {units: 'kilometers'});

// --- STORAGE & EXPIRY SYSTEM -----------------------------------------

// localStorage key
const CLAIM_STORAGE_KEY = "claimedHexesV1";

// Load stored claims or empty object
let claimedHexes = JSON.parse(localStorage.getItem(CLAIM_STORAGE_KEY) || "{}");

// Expiry in milliseconds (90 days)
const THREE_MONTHS = 90 * 24 * 60 * 60 * 1000;

// Check expiry and clean old claims
function cleanupExpiredClaims() {
  const now = Date.now();
  let changed = false;

  for (const hexId in claimedHexes) {
    if (now - claimedHexes[hexId] > THREE_MONTHS) {
      delete claimedHexes[hexId];
      changed = true;
    }
  }

  if (changed) {
    localStorage.setItem(CLAIM_STORAGE_KEY, JSON.stringify(claimedHexes));
  }
}
cleanupExpiredClaims();

// ----------------------------------------------------------------------

function hexStyle(claimed = false) {
  return {
    color: "#504806ff",
    weight: 1,
    fillColor: claimed ? "#f3d617" : "#bbb817ff",  // brighter when claimed
    fillOpacity: claimed ? 0.8 : 0.4
  };
}

// Add hexes with popup and menus
L.geoJSON(hexGrid, {
  style: (feature) => {
    const center = turf.center(feature).geometry.coordinates;
    const hexId = "hex-" + center[1].toFixed(5) + "-" + center[0].toFixed(5);
    const isClaimed = claimedHexes.hasOwnProperty(hexId);

    return hexStyle(isClaimed);
  },

  onEachFeature: function (feature, layer) {
    const center = turf.center(feature).geometry.coordinates;
    const hexId = "hex-" + center[1].toFixed(5) + "-" + center[0].toFixed(5);
    const bigMenuId = hexId + "-bigmenu";

    // Popup HTML
    const dropdownHtml = `
      <label for="${hexId}">Options for hive:</label><br/>
      <select id="${hexId}" class="hex-dropdown">
        <option value="info-${hexId}">Show Hive Info</option>
        <option value="claim-${hexId}">Claim Hive</option>
      </select>

      <div id="${bigMenuId}" style="display:none; margin-top:10px;">
        <label>Choose hive action:</label><br/>
        <select class="big-menu">
          <option value="">-- Select --</option>
          <option value="register">Register as Keeper</option>
          <option value="report">Report Hive Status</option>
          <option value="queen">Mark Queen Status</option>
          <option value="resources">Add Hive Resources</option>
        </select>
      </div>
    `;

    layer.bindPopup(dropdownHtml);

    // Attach popup logic
    layer.on("popupopen", () => {
      const dropdown = document.getElementById(hexId);
      const bigMenu = document.getElementById(bigMenuId);

      dropdown.addEventListener("change", (e) => {
        const val = e.target.value;

        if (val.startsWith("info")) {
          alert("Hive info at " + center[1].toFixed(5) + ", " + center[0].toFixed(5));
        }

        if (val.startsWith("claim")) {
          // Show large menu
          bigMenu.style.display = "block";

          // Record claim timestamp
          claimedHexes[hexId] = Date.now();
          localStorage.setItem(CLAIM_STORAGE_KEY, JSON.stringify(claimedHexes));

          // Change the hex colour
          layer.setStyle(hexStyle(true));
        }
      });
    });
  }

}).addTo(map);

</script>
</body>
</html>
