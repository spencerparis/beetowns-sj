<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8"/>
<meta name="viewport" content="width=device-width,initial-scale=1"/>
<title>Bee Towns - Hive Map</title>

<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"/>
<style>
html, body, #map { height: 100%; margin: 0; padding: 0; }
.hex-dropdown { font-size: 14px; padding: 4px; width: 160px; }
.placeholder-title { font-weight: bold; margin-bottom: 5px; display:block; }
.placeholder-input { width: 95%; padding: 6px; margin-bottom: 8px; font-size: 14px; box-sizing:border-box; }
.submit-btn { padding: 8px; width: 100%; font-size: 14px; font-weight:700; background:#2b8a3e; color:white; border:none; border-radius:4px; cursor:pointer; }
.submit-btn:disabled { background:#9fc6a6; cursor:not-allowed; }
.clear-btn { padding: 6px; width: 100%; font-size: 13px; margin-top:6px; background:#d9534f; color:white; border:none; border-radius:4px; cursor:pointer; }
.info-note { font-size:12px; color:#333; margin-top:6px; }
</style>
</head>
<body>
<div id="map"></div>

<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script src="https://unpkg.com/@turf/turf@6.5.0/turf.min.js"></script>

<script>
// Config
const HEX_SIZE_KM = 0.3;
const CLAIM_STORAGE_KEY = "beeTown_claimedHexes_v3";
const THREE_MONTHS_MS = 90*24*60*60*1000;

// Map init
const map = L.map('map').setView([50.3755, -4.1427], 14);
L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
  attribution:'&copy; OpenStreetMap contributors'
}).addTo(map);

// Locate user
map.locate({ setView:true, maxZoom:16, watch:false });
map.on("locationfound",(e)=>{L.marker(e.latlng).addTo(map).bindPopup("You are here").openPopup();});

// Load saved claims
let claimedHexes = {};
try{ claimedHexes = JSON.parse(localStorage.getItem(CLAIM_STORAGE_KEY) || "{}"); } catch(e){ claimedHexes={}; }
function persistClaims(){ localStorage.setItem(CLAIM_STORAGE_KEY, JSON.stringify(claimedHexes)); }
function cleanupExpiredClaims(){
  const now = Date.now();
  for(const id in claimedHexes){
    const r = claimedHexes[id];
    if(!r || !r.timestamp || (now - r.timestamp) > THREE_MONTHS_MS){
      delete claimedHexes[id];
    }
  }
  persistClaims();
}
cleanupExpiredClaims();

// Styles
function hexStyle(claimed=false){
  return { color:"#504806ff", weight:1, fillColor: claimed?"#f3d617":"#bbb817ff", fillOpacity: claimed?0.8:0.4 };
}

// Load real Cornwall boundary GeoJSON
fetch("https://mapit.mysociety.org/area/2250.geojson")
  .then(res => res.json())
  .then(cornwallBoundary => {

    // Build hex grid across that boundary’s bounds
    const bbox = turf.bbox(cornwallBoundary);
    let hexGrid = turf.hexGrid(bbox, HEX_SIZE_KM, { units: "kilometers" });

    // Keep only hexes whose centers are inside real Cornwall
    hexGrid.features = hexGrid.features.filter(f => {
      const center = turf.center(f);
      return turf.booleanPointInPolygon(center, cornwallBoundary);
    });

    // Add grid to map
    L.geoJSON(hexGrid, {
      style: feature=>{
        const hexId = makeHexId(turf.center(feature).geometry.coordinates);
        return hexStyle(!!claimedHexes[hexId]);
      },
      onEachFeature: function(feature, layer){
        const center = turf.center(feature).geometry.coordinates;
        const hexId = makeHexId(center);
        const formId = hexId + "-form";
        const submitBtnId = hexId + "-submit";
        const clearBtnId = hexId + "-clear";
        const dropdownId = hexId + "-dd";

        const isClaimed = !!claimedHexes[hexId];
        const dropdownHtml = isClaimed ? `
          <select id="${dropdownId}" class="hex-dropdown">
            <option value="">-- Select --</option>
            <option value="access-${hexId}">Access Hive</option>
            <option value="unclaim-${hexId}">Unclaim Hive</option>
          </select>` :
        `<select id="${dropdownId}" class="hex-dropdown">
            <option value="">-- Select --</option>
            <option value="info-${hexId}">Hive Info</option>
            <option value="claim-${hexId}">Claim Hive</option>
        </select>`;

        const popupHtml = `
          ${dropdownHtml}
          <div id="${formId}" style="display:none; margin-top:10px;">
            <span class="placeholder-title">Hive Data</span>
            <input id="${formId}-1" class="placeholder-input" placeholder="Field 1">
            <input id="${formId}-2" class="placeholder-input" placeholder="Field 2">
            <input id="${formId}-3" class="placeholder-input" placeholder="Field 3">
            <input id="${formId}-4" class="placeholder-input" placeholder="Field 4">
            <button id="${submitBtnId}" class="submit-btn" disabled>Submit</button>
            <button id="${clearBtnId}" class="clear-btn" style="display:none;">Clear Claim</button>
            <div class="info-note">Claim expires after 90 days</div>
          </div>`;

        layer.bindPopup(popupHtml);

        layer.on("popupopen", ()=>{
          setTimeout(()=>{
            const dropdown = document.getElementById(dropdownId);
            const formWrapper = document.getElementById(formId);
            const input1 = document.getElementById(formId+"-1");
            const input2 = document.getElementById(formId+"-2");
            const input3 = document.getElementById(formId+"-3");
            const input4 = document.getElementById(formId+"-4");
            const submitBtn = document.getElementById(submitBtnId);
            const clearBtn = document.getElementById(clearBtnId);

            const saved = claimedHexes[hexId] && claimedHexes[hexId].form;

            if(saved){
              input1.value=saved.f1||"";
              input2.value=saved.f2||"";
              input3.value=saved.f3||"";
              input4.value=saved.f4||"";
              input1.readOnly=input2.readOnly=input3.readOnly=input4.readOnly=true;
              submitBtn.style.display="none";
              clearBtn.style.display="block";
              formWrapper.style.display="block";
              layer.setStyle(hexStyle(true));
            } else {
              input1.value=input2.value=input3.value=input4.value="";
              input1.readOnly=input2.readOnly=input3.readOnly=input4.readOnly=false;
              submitBtn.style.display="inline-block";
              submitBtn.disabled=true;
              clearBtn.style.display="none";
            }

            function validate(){
              submitBtn.disabled = !(input1.value.trim()&&input2.value.trim()&&input3.value.trim()&&input4.value.trim());
            }
            [input1,input2,input3,input4].forEach(i=>i.oninput=validate);

            dropdown.onchange = e => {
              const val = e.target.value;
              if(val.startsWith("info")) alert("Hive info at " + center[1].toFixed(5) + ", " + center[0].toFixed(5));
              else if(val.startsWith("claim") || val.startsWith("access")) formWrapper.style.display="block";
              else if(val.startsWith("unclaim")){
                delete claimedHexes[hexId];
                persistClaims();
                formWrapper.style.display="none";
                layer.setStyle(hexStyle(false));
                dropdown.value="";
              }
            };

            submitBtn.onclick = () => {
              claimedHexes[hexId] = { timestamp: Date.now(), form:{
                f1:input1.value.trim(),
                f2:input2.value.trim(),
                f3:input3.value.trim(),
                f4:input4.value.trim()
              }};
              persistClaims();
              input1.readOnly=input2.readOnly=input3.readOnly=input4.readOnly=true;
              submitBtn.style.display="none";
              clearBtn.style.display="block";
              layer.setStyle(hexStyle(true));
              dropdown.value="";
            };

            clearBtn.onclick = () => {
              delete claimedHexes[hexId];
              persistClaims();
              input1.readOnly=input2.readOnly=input3.readOnly=input4.readOnly=false;
              submitBtn.style.display="inline-block";
              submitBtn.disabled=true;
              clearBtn.style.display="none";
              formWrapper.style.display="none";
              layer.setStyle(hexStyle(false));
              dropdown.value="";
            };

          },50);
        });
      }
    }).addTo(map);

  })
  .catch(err => console.error("Error loading Cornwall boundary:", err));

function makeHexId(coords){ return "hex-" + coords[1].toFixed(5) + "-" + coords[0].toFixed(5); }
</script>

</body>
</html>
