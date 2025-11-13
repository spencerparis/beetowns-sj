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

// Add OSM tiles
L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
  attribution: '&copy; OpenStreetMap contributors'
}).addTo(map);

// Try to center on user location once at load
map.locate({ setView: true, maxZoom: 16, watch: false });

// When location is found, drop a marker
map.on("locationfound", (e) => {
  const userMarker = L.marker(e.latlng).addTo(map);
  userMarker.bindPopup("You are here").openPopup();
});

map.on("locationerror", () => {
  console.warn("Could not get user location; showing fallback center.");
});

// Time constant: 3 months (approx 90 days)
const THREE_MONTHS_MS = 1000 * 60 * 60 * 24 * 90;

// Style for hexagons (default)
function hexStyle() {
  return {
    color: "#504806ff",
    weight: 1,
    fillColor: "#bbb817ff",
    fillOpacity: 0.4
  };
}

// Save hive claim state
function saveHiveClaim(hexId, claimed) {
  const record = {
    claimed: claimed,
    timestamp: Date.now()
  };
  localStorage.setItem(hexId, JSON.stringify(record));
}

// Load hive claim state
function loadHiveClaim(hexId) {
  const record = localStorage.getItem(hexId);
  if (!record) return null;
  try {
    const parsed = JSON.parse(record);
    if (Date.now() - parsed.timestamp < THREE_MONTHS_MS) {
      return parsed.claimed;
    } else {
      // Expired -> remove
      localStorage.removeItem(hexId);
    }
  } catch (e) {
    console.warn("Corrupt localStorage entry for", hexId);
  }
  return null;
}

// Generate hex grid (300m ~ 0.3km)
const hexGrid = turf.hexGrid(cornwallBbox, 0.3, {units: 'kilometers'});

// Add hexes with dropdown popup
L.geoJSON(hexGrid, {
  style: hexStyle,
  onEachFeature: function (feature, layer) {
    const center = turf.center(feature).geometry.coordinates;

    // Unique ID per hex based on center coords
    const hexId = "hex-" + center[1].toFixed(5) + "-" + center[0].toFixed(5);

    // Check if this hex is already claimed in storage
    const claimed = loadHiveClaim(hexId);
    if (claimed) {
      layer.setStyle({ fillOpacity: 0.8 });
    }

    // Dropdown HTML
    const dropdownHtml = `
      <label for="${hexId}">Options for hex:</label><br/>
      <select id="${hexId}" class="hex-dropdown">
        <option value="info-${hexId}">Show Hive Info</option>
        <option value="action-${hexId}">Claim Hive</option>
      </select>
    `;

    // Bind popup with dropdown
    layer.bindPopup(dropdownHtml);

    // Attach dropdown logic when popup opens
    layer.on("popupopen", () => {
      const dropdown = document.getElementById(hexId);
      dropdown.addEventListener("change", (e) => {
        const val = e.target.value;
        if (val.startsWith("info")) {
          alert("Info about hex at " + center[1].toFixed(5) + ", " + center[0].toFixed(5));
        } else if (val.startsWith("action")) {
          // Claim hive (change opacity + save state)
          layer.setStyle({ fillOpacity: 0.8 });
          saveHiveClaim(hexId, true);
        }
      });
    });
  }
}).addTo(map);

</script>
</body>
</html>