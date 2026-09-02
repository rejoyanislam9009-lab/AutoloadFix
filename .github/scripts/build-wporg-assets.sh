#!/usr/bin/env bash
set -euo pipefail

OUT="${1:-.wordpress-org}"
mkdir -p "$OUT"

cat > "$OUT/icon.svg" <<'SVG'
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512">
  <defs>
    <linearGradient id="bg" x1="0" y1="0" x2="1" y2="1"><stop stop-color="#071522"/><stop offset="1" stop-color="#0d3441"/></linearGradient>
    <linearGradient id="g" x1="0" y1="0" x2="1" y2="1"><stop stop-color="#4be7c2"/><stop offset=".52" stop-color="#46c9ff"/><stop offset="1" stop-color="#8175ff"/></linearGradient>
  </defs>
  <rect width="512" height="512" rx="112" fill="url(#bg)"/>
  <circle cx="256" cy="250" r="172" fill="#0b2130" stroke="url(#g)" stroke-width="14"/>
  <path d="M155 315V171h48v100l64-100h58l-76 107 85 37h-72l-51-24-8 11v13z" fill="url(#g)"/>
  <path d="M144 347h59l20-34 31 57 30-55 24 32h60" fill="none" stroke="#6ff0cd" stroke-width="13" stroke-linecap="round" stroke-linejoin="round"/>
  <circle cx="368" cy="347" r="8" fill="#6ff0cd"/>
</svg>
SVG

cat > /tmp/loadvexa-banner.svg <<'SVG'
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1544 500">
  <defs>
    <linearGradient id="bg" x1="0" y1="0" x2="1" y2="1"><stop stop-color="#071522"/><stop offset=".58" stop-color="#0a2737"/><stop offset="1" stop-color="#113d48"/></linearGradient>
    <linearGradient id="g" x1="0" y1="0" x2="1" y2="1"><stop stop-color="#4be7c2"/><stop offset=".52" stop-color="#46c9ff"/><stop offset="1" stop-color="#8175ff"/></linearGradient>
  </defs>
  <rect width="1544" height="500" fill="url(#bg)"/>
  <circle cx="1268" cy="250" r="310" fill="none" stroke="#2f6472" stroke-width="2" opacity=".55"/>
  <circle cx="1268" cy="250" r="220" fill="#0b2130" stroke="url(#g)" stroke-width="15"/>
  <path d="M1160 310V174h46v94l61-94h56l-72 101 82 35h-69l-50-23-8 11v12z" fill="url(#g)"/>
  <path d="M1150 340h55l19-32 30 54 29-52 23 30h66" fill="none" stroke="#6ff0cd" stroke-width="12" stroke-linecap="round"/>
  <text x="118" y="190" fill="#78efd3" font-family="Arial,Helvetica,sans-serif" font-size="28" font-weight="700" letter-spacing="6">PERFORMANCE DIAGNOSTICS</text>
  <text x="112" y="290" fill="#ffffff" font-family="Arial,Helvetica,sans-serif" font-size="86" font-weight="800">Loadvexa</text>
  <text x="118" y="354" fill="#bcd0d8" font-family="Arial,Helvetica,sans-serif" font-size="32">Autoload &amp; Cache Diagnostics</text>
  <rect x="118" y="394" width="440" height="3" rx="2" fill="url(#g)"/>
</svg>
SVG

rsvg-convert -w 128 -h 128 "$OUT/icon.svg" -o "$OUT/icon-128x128.png"
rsvg-convert -w 256 -h 256 "$OUT/icon.svg" -o "$OUT/icon-256x256.png"
rsvg-convert -w 1544 -h 500 /tmp/loadvexa-banner.svg -o "$OUT/banner-1544x500.png"
rsvg-convert -w 772 -h 250 /tmp/loadvexa-banner.svg -o "$OUT/banner-772x250.png"

screen_start() {
  local file="$1" eyebrow="$2" title="$3" subtitle="$4"
  cat > "$file" <<SVG
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1365 768">
<defs><linearGradient id="accent" x1="0" y1="0" x2="1" y2="1"><stop stop-color="#35d9b3"/><stop offset="1" stop-color="#3b82f6"/></linearGradient></defs>
<rect width="1365" height="768" fill="#eef1f4"/>
<rect width="220" height="768" fill="#17212b"/>
<text x="34" y="58" fill="#ffffff" font-family="Arial" font-size="25" font-weight="700">Loadvexa</text>
<text x="34" y="103" fill="#93a4b2" font-family="Arial" font-size="16">Overview</text><text x="34" y="140" fill="#93a4b2" font-family="Arial" font-size="16">Monitor &amp; Tools</text><text x="34" y="177" fill="#93a4b2" font-family="Arial" font-size="16">Cache Advisor</text><text x="34" y="214" fill="#93a4b2" font-family="Arial" font-size="16">Site Scanner</text><text x="34" y="251" fill="#93a4b2" font-family="Arial" font-size="16">Optimization Profiles</text>
<rect x="220" y="0" width="1145" height="66" fill="#ffffff"/><text x="255" y="42" fill="#5a6874" font-family="Arial" font-size="15">Dashboard  /  Loadvexa</text>
<rect x="255" y="94" width="1070" height="145" rx="18" fill="#ffffff" stroke="#dbe1e6"/>
<text x="286" y="128" fill="#6a7984" font-family="Arial" font-size="13" font-weight="700" letter-spacing="2">$eyebrow</text>
<text x="286" y="174" fill="#1c2731" font-family="Arial" font-size="34" font-weight="700">$title</text>
<text x="286" y="208" fill="#697985" font-family="Arial" font-size="17">$subtitle</text>
<rect x="1212" y="118" width="78" height="34" rx="17" fill="#edf7ff"/><text x="1232" y="141" fill="#2777aa" font-family="Arial" font-size="13" font-weight="700">v1.4.1</text>
SVG
}

screen_end() { echo '</svg>' >> "$1"; }

# Screenshot 1: health overview.
f="$OUT/screenshot-1.svg"; screen_start "$f" "LOADVEXA PERFORMANCE TOOLKIT" "Autoload Health Overview" "Measure autoload pressure, review large options, and make cautious snapshot-backed changes."
cat >> "$f" <<'SVG'
<rect x="255" y="264" width="1070" height="190" rx="18" fill="#101a20"/>
<circle cx="375" cy="360" r="72" fill="none" stroke="#35434b" stroke-width="12"/><circle cx="375" cy="360" r="72" fill="none" stroke="#55dfb7" stroke-width="12" stroke-dasharray="430 30" transform="rotate(-90 375 360)"/>
<text x="375" y="359" text-anchor="middle" fill="#fff" font-family="Arial" font-size="42" font-weight="700">100</text><text x="375" y="386" text-anchor="middle" fill="#90a0aa" font-family="Arial" font-size="14">/100</text>
<rect x="494" y="305" width="78" height="28" rx="14" fill="#d9f8e9"/><text x="512" y="325" fill="#16814e" font-family="Arial" font-size="13" font-weight="700">Healthy</text><text x="494" y="370" fill="#fff" font-family="Arial" font-size="31" font-weight="700">Autoload health score</text><text x="494" y="402" fill="#aab7bf" font-family="Arial" font-size="16">Large or unnecessary autoload entries can increase memory use and database work.</text>
<g font-family="Arial"><rect x="255" y="478" width="250" height="116" rx="16" fill="#fff"/><text x="278" y="510" fill="#78858e" font-size="14">Total autoload</text><text x="278" y="553" fill="#26333d" font-size="30" font-weight="700">180.5 KB</text><rect x="524" y="478" width="250" height="116" rx="16" fill="#fff"/><text x="547" y="510" fill="#78858e" font-size="14">Autoloaded options</text><text x="547" y="553" fill="#26333d" font-size="30" font-weight="700">590</text><rect x="793" y="478" width="250" height="116" rx="16" fill="#fff"/><text x="816" y="510" fill="#78858e" font-size="14">Large entries</text><text x="816" y="553" fill="#26333d" font-size="30" font-weight="700">0</text><rect x="1062" y="478" width="263" height="116" rx="16" fill="#fff"/><text x="1085" y="510" fill="#78858e" font-size="14">Review candidates</text><text x="1085" y="553" fill="#26333d" font-size="30" font-weight="700">0</text></g>
<rect x="255" y="615" width="1070" height="118" rx="16" fill="#fff"/><text x="278" y="650" fill="#26333d" font-family="Arial" font-size="20" font-weight="700">Largest autoloaded options</text><line x1="278" y1="674" x2="1295" y2="674" stroke="#e3e8eb"/><text x="290" y="704" fill="#596872" font-family="Arial" font-size="14">rewrite_rules</text><text x="606" y="704" fill="#596872" font-family="Arial" font-size="14">46.1 KB</text><rect x="910" y="686" width="80" height="25" rx="12" fill="#e7f7ee"/><text x="929" y="704" fill="#217a50" font-family="Arial" font-size="12" font-weight="700">Protected</text>
SVG
screen_end "$f"

# Screenshot 2: Monitor & Tools.
f="$OUT/screenshot-2.svg"; screen_start "$f" "LOADVEXA MONITORING" "Monitor &amp; Tools" "Track autoload growth, maintain watch lists, export diagnostics, and configure safety controls."
cat >> "$f" <<'SVG'
<g font-family="Arial"><rect x="255" y="264" width="250" height="120" rx="16" fill="#fff"/><text x="278" y="298" fill="#78858e" font-size="14">Current autoload</text><text x="278" y="342" fill="#23323c" font-size="31" font-weight="700">180.5 KB</text><rect x="524" y="264" width="250" height="120" rx="16" fill="#fff"/><text x="547" y="298" fill="#78858e" font-size="14">Last audit delta</text><text x="547" y="342" fill="#23323c" font-size="31" font-weight="700">0 B</text><rect x="793" y="264" width="250" height="120" rx="16" fill="#fff"/><text x="816" y="298" fill="#78858e" font-size="14">Watched options</text><text x="816" y="342" fill="#23323c" font-size="31" font-weight="700">2</text><rect x="1062" y="264" width="263" height="120" rx="16" fill="#fff"/><text x="1085" y="298" fill="#78858e" font-size="14">Next audit</text><text x="1085" y="342" fill="#23323c" font-size="24" font-weight="700">Tomorrow</text></g>
<rect x="255" y="408" width="650" height="285" rx="16" fill="#fff"/><text x="278" y="444" fill="#23323c" font-family="Arial" font-size="20" font-weight="700">Autoload trend</text><line x1="290" y1="642" x2="870" y2="642" stroke="#dce3e7"/><line x1="290" y1="490" x2="290" y2="642" stroke="#dce3e7"/><polyline points="310,592 420,568 530,575 640,515 750,536 855,488" fill="none" stroke="#368de7" stroke-width="5"/><g fill="#368de7"><circle cx="310" cy="592" r="6"/><circle cx="420" cy="568" r="6"/><circle cx="530" cy="575" r="6"/><circle cx="640" cy="515" r="6"/><circle cx="750" cy="536" r="6"/><circle cx="855" cy="488" r="6"/></g>
<rect x="929" y="408" width="396" height="285" rx="16" fill="#fff"/><text x="952" y="444" fill="#23323c" font-family="Arial" font-size="20" font-weight="700">Review workspace</text><rect x="952" y="470" width="350" height="42" rx="8" fill="#f5f7f9" stroke="#d9e0e5"/><text x="970" y="497" fill="#8a969e" font-family="Arial" font-size="14">Search option or owner...</text><text x="952" y="548" fill="#5b6872" font-family="Arial" font-size="14">rewrite_rules</text><text x="1190" y="548" fill="#217a50" font-family="Arial" font-size="13" font-weight="700">Protected</text><line x1="952" y1="562" x2="1302" y2="562" stroke="#e4e9ec"/><text x="952" y="594" fill="#5b6872" font-family="Arial" font-size="14">theme_options</text><text x="1190" y="594" fill="#2877a7" font-family="Arial" font-size="13" font-weight="700">Review</text><line x1="952" y1="608" x2="1302" y2="608" stroke="#e4e9ec"/><text x="952" y="640" fill="#5b6872" font-family="Arial" font-size="14">cron</text><text x="1190" y="640" fill="#217a50" font-family="Arial" font-size="13" font-weight="700">Protected</text>
SVG
screen_end "$f"

# Screenshot 3: Cache Advisor.
f="$OUT/screenshot-3.svg"; screen_start "$f" "LOADVEXA CACHE INTELLIGENCE" "Cache &amp; Optimization Advisor" "Detect cache layers, avoid overlapping page caches, purge supported caches, and verify the front end."
cat >> "$f" <<'SVG'
<rect x="255" y="264" width="1070" height="110" rx="16" fill="#fff" stroke="#dbe2e6"/><rect x="255" y="264" width="7" height="110" rx="4" fill="#3189d8"/><text x="286" y="298" fill="#72808a" font-family="Arial" font-size="13" font-weight="700">PRIMARY RECOMMENDATION</text><text x="286" y="334" fill="#25323c" font-family="Arial" font-size="25" font-weight="700">LiteSpeed Cache matches this server well</text><text x="286" y="358" fill="#73818b" font-family="Arial" font-size="14">Keep one primary full-page cache and use the detected plugin's native purge controls.</text>
<g font-family="Arial"><rect x="255" y="398" width="335" height="112" rx="16" fill="#fff"/><text x="280" y="431" fill="#78858e" font-size="13">AUTOLOAD HEALTH</text><text x="280" y="470" fill="#23323c" font-size="28" font-weight="700">180.5 KB</text><rect x="610" y="398" width="335" height="112" rx="16" fill="#fff"/><text x="635" y="431" fill="#78858e" font-size="13">SERVER</text><text x="635" y="470" fill="#23323c" font-size="25" font-weight="700">LiteSpeed / OpenLiteSpeed</text><rect x="965" y="398" width="360" height="112" rx="16" fill="#fff"/><text x="990" y="431" fill="#78858e" font-size="13">DETECTED CACHE STACK</text><text x="990" y="470" fill="#23323c" font-size="28" font-weight="700">1 primary cache</text></g>
<rect x="255" y="534" width="1070" height="188" rx="16" fill="#fff"/><text x="280" y="570" fill="#23323c" font-family="Arial" font-size="20" font-weight="700">Cache layers</text><g font-family="Arial"><rect x="280" y="590" width="235" height="98" rx="12" fill="#edf7ff"/><text x="300" y="620" fill="#218058" font-size="12" font-weight="700">Detected</text><text x="300" y="650" fill="#26343d" font-size="16" font-weight="700">Full-page cache</text><text x="300" y="674" fill="#73818b" font-size="13">LiteSpeed Cache</text><rect x="535" y="590" width="235" height="98" rx="12" fill="#f7f8fa"/><text x="555" y="620" fill="#687680" font-size="12" font-weight="700">Not detected</text><text x="555" y="650" fill="#26343d" font-size="16" font-weight="700">Object cache</text><rect x="790" y="590" width="235" height="98" rx="12" fill="#f7f8fa"/><text x="810" y="620" fill="#687680" font-size="12" font-weight="700">Optional</text><text x="810" y="650" fill="#26343d" font-size="16" font-weight="700">Asset optimization</text><rect x="1045" y="590" width="255" height="98" rx="12" fill="#effaf5"/><text x="1065" y="620" fill="#218058" font-size="12" font-weight="700">Verified</text><text x="1065" y="650" fill="#26343d" font-size="16" font-weight="700">Front-end cache signal</text></g>
SVG
screen_end "$f"

# Screenshot 4: Site Scanner.
f="$OUT/screenshot-4.svg"; screen_start "$f" "LOADVEXA PAGE-BY-PAGE DIAGNOSTICS" "Site Problem Scanner" "Scan public pages in safe batches, inspect cache/response problems, and re-check the same URLs after fixes."
cat >> "$f" <<'SVG'
<g font-family="Arial"><rect x="255" y="264" width="190" height="98" rx="14" fill="#fff"/><text x="276" y="296" fill="#78858e" font-size="13">Scanned</text><text x="276" y="335" fill="#26343d" font-size="28" font-weight="700">147</text><rect x="460" y="264" width="190" height="98" rx="14" fill="#fff"/><text x="481" y="296" fill="#78858e" font-size="13">Healthy</text><text x="481" y="335" fill="#218058" font-size="28" font-weight="700">144</text><rect x="665" y="264" width="190" height="98" rx="14" fill="#fff"/><text x="686" y="296" fill="#78858e" font-size="13">Needs review</text><text x="686" y="335" fill="#b47711" font-size="28" font-weight="700">3</text><rect x="870" y="264" width="190" height="98" rx="14" fill="#fff"/><text x="891" y="296" fill="#78858e" font-size="13">Critical</text><text x="891" y="335" fill="#26343d" font-size="28" font-weight="700">0</text><rect x="1075" y="264" width="250" height="98" rx="14" fill="#fff"/><text x="1096" y="296" fill="#78858e" font-size="13">Verified fixed</text><text x="1096" y="335" fill="#26343d" font-size="28" font-weight="700">0</text></g>
<rect x="255" y="386" width="1070" height="74" rx="14" fill="#fff"/><text x="280" y="418" fill="#26343d" font-family="Arial" font-size="15" font-weight="700">Scan progress</text><rect x="280" y="432" width="1018" height="9" rx="5" fill="#e3e8eb"/><rect x="280" y="432" width="1018" height="9" rx="5" fill="url(#accent)"/><text x="1242" y="418" fill="#5f6d76" font-family="Arial" font-size="13">100%</text>
<rect x="255" y="484" width="1070" height="235" rx="16" fill="#fff"/><text x="280" y="520" fill="#26343d" font-family="Arial" font-size="20" font-weight="700">Page-by-page results</text><g font-family="Arial" font-size="13"><text x="285" y="556" fill="#7b8790">Page</text><text x="750" y="556" fill="#7b8790">Response</text><text x="905" y="556" fill="#7b8790">Cache</text><text x="1112" y="556" fill="#7b8790">Status</text><line x1="280" y1="568" x2="1300" y2="568" stroke="#e1e6e9"/><text x="285" y="600" fill="#34434d" font-weight="700">Home</text><text x="750" y="600" fill="#34434d">200 · 18 ms</text><text x="905" y="600" fill="#218058">HIT · Warm verified</text><text x="1112" y="600" fill="#218058" font-weight="700">Healthy</text><line x1="280" y1="614" x2="1300" y2="614" stroke="#e9edef"/><text x="285" y="646" fill="#34434d" font-weight="700">Premium Checkout</text><text x="750" y="646" fill="#34434d">200 · 1.35 s</text><text x="905" y="646" fill="#6e7c86">UNKNOWN</text><text x="1112" y="646" fill="#b47711" font-weight="700">Review</text><line x1="280" y1="660" x2="1300" y2="660" stroke="#e9edef"/><text x="285" y="692" fill="#34434d" font-weight="700">Shop</text><text x="750" y="692" fill="#34434d">200 · 19 ms</text><text x="905" y="692" fill="#218058">HIT · Warm verified</text><text x="1112" y="692" fill="#218058" font-weight="700">Healthy</text></g>
SVG
screen_end "$f"

# Screenshot 5: Optimization Profiles.
f="$OUT/screenshot-5.svg"; screen_start "$f" "LOADVEXA GUIDED CONFIGURATION" "Optimization Profiles" "Turn verified scanner findings into conservative, site-specific import guidance for supported cache plugins."
cat >> "$f" <<'SVG'
<g font-family="Arial"><rect x="255" y="264" width="250" height="100" rx="14" fill="#fff"/><text x="278" y="297" fill="#78858e" font-size="13">Detected cache plugin</text><text x="278" y="334" fill="#26343d" font-size="20" font-weight="700">LiteSpeed Cache</text><rect x="524" y="264" width="250" height="100" rx="14" fill="#fff"/><text x="547" y="297" fill="#78858e" font-size="13">Import format</text><text x="547" y="334" fill="#26343d" font-size="18" font-weight="700">Native .data settings</text><rect x="793" y="264" width="250" height="100" rx="14" fill="#fff"/><text x="816" y="297" fill="#78858e" font-size="13">Scan readiness</text><text x="816" y="334" fill="#218058" font-size="20" font-weight="700">Complete</text><rect x="1062" y="264" width="263" height="100" rx="14" fill="#fff"/><text x="1085" y="297" fill="#78858e" font-size="13">Safe profile changes</text><text x="1085" y="334" fill="#26343d" font-size="20" font-weight="700">0</text></g>
<rect x="255" y="388" width="1070" height="98" rx="16" fill="#fff"/><rect x="255" y="388" width="7" height="98" rx="4" fill="#d8a421"/><text x="286" y="425" fill="#26343d" font-family="Arial" font-size="21" font-weight="700">No safe import change is currently required</text><text x="286" y="454" fill="#70808a" font-family="Arial" font-size="14">Manual findings remain visible, but the scan does not justify an automatic cache-setting mutation.</text>
<rect x="255" y="510" width="660" height="206" rx="16" fill="#fff"/><text x="280" y="546" fill="#26343d" font-family="Arial" font-size="20" font-weight="700">Import &amp; verify workflow</text><g fill="#5f6e78" font-family="Arial" font-size="14"><text x="280" y="582">1. Complete a fresh Site Problem Scanner run.</text><text x="280" y="615">2. Review current vs recommended settings.</text><text x="280" y="648">3. Import only when a high-confidence change exists.</text><text x="280" y="681">4. Purge cache and re-check affected pages.</text></g>
<rect x="939" y="510" width="386" height="206" rx="16" fill="#fff"/><text x="964" y="546" fill="#26343d" font-family="Arial" font-size="20" font-weight="700">Manual findings</text><rect x="964" y="571" width="336" height="45" rx="9" fill="#f1f7fb"/><text x="980" y="599" fill="#357ca6" font-family="Arial" font-size="13" font-weight="700">Dynamic cache status · Info</text><rect x="964" y="628" width="336" height="45" rx="9" fill="#fff8e6"/><text x="980" y="656" fill="#a16a09" font-family="Arial" font-size="13" font-weight="700">Slow server response · Review</text>
SVG
screen_end "$f"

for n in 1 2 3 4 5; do
  rsvg-convert -w 1365 -h 768 "$OUT/screenshot-${n}.svg" -o "$OUT/screenshot-${n}.png"
  rm "$OUT/screenshot-${n}.svg"
done

rm -f /tmp/loadvexa-banner.svg

echo "Generated WordPress.org assets in $OUT"
