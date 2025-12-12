<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Bee Towns - Hive Map</title>

  <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"/>
  <style>
    body, html, #map { height: 100%; margin: 0; padding: 0; }

    .hex-dropdown {
      font-size: 14px;
      padding: 4px;
      width: 160px;
    }

    .placeholder-title {
      font-weight: bold;
      margin-bottom: 5px;
      display: block;
    }

    .placeholder-input {
      width: 95%;
      padding: 5px;
      margin-bottom: 8px;
      font-size: 14px;
    }

    .submit-btn {
      padding: 6px;
      width: 100%;
      font-size: 14px;
      font-weight: bold;
      background: #ffd500;
      border: 1px solid #aa9800;
      border-radius: 4px;
      cursor: pointer;
    }

    .submit-btn:disabled {
      background: #ccc;
      border: 1px solid #888;
      cursor: not-allowed;
    }
  </style>
</head>
<body>
<div id="map"></div>

<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script src="https://unpkg.com/@turf/turf@6.5.0/turf.min.js"></script>

<script>
// -----------------------------------------------------------
// Cornwall bounding box
// -----------------------------------------------------------
const cornwallBbox = [-5.75, 49.9, -3.9, 51.1];

// -----------------------------------------------------------
// Map
// -----------------------------------------------------------
const map = L.map('map').setView([50.3755, -4.1427], 14);

L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
  attribution: '&copy; OpenStreetMap contributors'
}).addTo(map);

// -----------------------------------------------------------
// User location
// -----------------------------------------------------------
map.locate({ setView: true, maxZoom: 16 });

map.on("locationfound", (e) => {
  L.marker(e.latlng).addTo(map).bindPopup("You are here").openPopup();
});

// -----------------------------------------------------------
// Local storage system for 3-month expiry
// -----------------------------------------------------------
const CLAIM_STORAGE_KEY = "claimedHexesV1";
let claimedHexes = JSON.parse(localStorage.getItem(CLAIM_STORAGE_KEY) || "{}");

const THREE_MONTHS = 90 * 24 * 60 * 60 * 1000;

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

// -----------------------------------------------------------
// Hex styling
// -----------------------------------------------------------
function hexStyle(claimed = false) {
  return {
    color: "#504806ff",
    weight: 1,
    fillColor: claimed ? "#f3d617" : "#bbb817ff",
    fillOpacity: claimed ? 0.8 : 0.4
  };
}

// -----------------------------------------------------------
// Generate grid of 300m hexagons
// -----------------------------------------------------------
const hexGrid = turf.hexGrid(cornwallBbox, 0.3, { units: "kilometers" });

// -----------------------------------------------------------
// Add hexagons to map
// -----------------------------------------------------------
L.geoJSON(hexGrid, {
  style: (feature) => {
    const center = turf.center(feature).geometry.coordinates;
    const hexId = "hex-" + center[1].toFixed(5) + "-" + center[0].toFixed(5);
    const isClaimed = claimedHexes.hasOwnProperty(hexId);
    return hexStyle(isClaimed);
  },

  onEachFeature: function(feature, layer) {
    const center = turf.center(feature).geometry.coordinates;
    const hexId = "hex-" + center[1].toFixed(5) + "-" + center[0].toFixed(5);
    const formId = hexId + "-form";
    const submitId = hexId + "-submit";

    // Popup content
    const popupHtml = `
      <label for="${hexId}">Options for hive:</label><br/>
      <select id="${hexId}" class="hex-dropdown">
        <option value="info-${hexId}">Show Hive Info</option>
        <option value="claim-${hexId}">Claim Hive</option>
      </select>

      <div id="${formId}" style="display:none; margin-top:10px;">
        <span class="placeholder-title">Submit Hive Details</span>

        <input class="placeholder-input" id="${formId}-1" type="text" placeholder="Placeholder 1">
        <input class="placeholder-input" id="${formId}-2" type="text" placeholder="Placeholder 2">
        <input class="placeholder-input" id="${formId}-3" type="text" placeholder="Placeholder 3">
        <input class="placeholder-input" id="${formId}-4" type="text" placeholder="Placeholder 4">

        <button id="${submitId}" class="submit-btn" disabled>Submit</button>
      </div>
    `;

    layer.bindPopup(popupHtml);

    // -----------------------------------------------------------
    // Popup logic
    // -----------------------------------------------------------
    layer.on("popupopen", () => {
      setTimeout(() => {
        const dropdown = document.getElementById(hexId);
        const formDiv = document.getElementById(formId);

        const input1 = document.getElementById(formId + "-1");
        const input2 = document.getElementById(formId + "-2");
        const input3 = document.getElementById(formId + "-3");
        const input4 = document.getElementById(formId + "-4");
        const submitBtn = document.getElementById(submitId);

        if (!dropdown) return;

        // Enable submit only when all fields are filled
        function validateForm() {
          const allFilled =
            input1.value.trim() &&
            input2.value.trim() &&
            input3.value.trim() &&
            input4.value.trim();

          submitBtn.disabled = !allFilled;
        }

        // Watch input changes
        [input1, input2, input3, input4].forEach(input => {
          input.addEventListener("input", validateForm);
        });

        // Dropdown handles showing the form
        dropdown.addEventListener("change", (e) => {
          const val = e.target.value;

          if (val.startsWith("info")) {
            alert("Hive info at " + center[1].toFixed(5) + ", " + center[0].toFixed(5));
          }

          if (val.startsWith("claim")) {
            formDiv.style.display = "block";
          }
        });

        // -----------------------------------------------------------
        // Submit button logic (claim only AFTER valid submission)
        // -----------------------------------------------------------
        submitBtn.addEventListener("click", () => {
          // Safety check
          if (submitBtn.disabled) return;

          // Save timestamp
          claimedHexes[hexId] = Date.now();
          localStorage.setItem(CLAIM_STORAGE_KEY, JSON.stringify(claimedHexes));

          // Change hex style
          layer.setStyle(hexStyle(true));

          submitBtn.innerText = "Submitted!";
          submitBtn.disabled = true;
        });
      }, 50);
    });
  }
}).addTo(map);

</script>
</body>
</html>
