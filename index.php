<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>WORLD_GEN // TMF.SPACE_CORE</title>
    <style>
        /* --- TMF.SPACE THEME --- */
        body { 
            background: #000; color: #00f0ff; 
            font-family: 'Courier New', Courier, monospace; 
            margin: 0; display: flex; flex-direction: column; align-items: center; 
            overflow-x: hidden;
        }

        h1 { 
            font-size: 1.5rem; letter-spacing: 4px; text-transform: uppercase; 
            margin: 20px 0; text-shadow: 0 0 10px #00f0ff; 
        }

        .toolbar { 
            background: rgba(0, 20, 30, 0.8); width: 90%; max-width: 1100px; padding: 20px; 
            display: flex; flex-wrap: wrap; justify-content: space-between; gap: 15px; 
            border: 1px solid #00f0ff; box-shadow: 0 0 15px rgba(0, 240, 255, 0.2); 
        }

        .control-group { display: flex; flex-direction: column; gap: 8px; }
        label { font-size: 10px; opacity: 0.7; text-transform: uppercase; }
        
        select, input { 
            background: #000; color: #00f0ff; border: 1px solid #00f0ff; 
            padding: 8px; font-family: monospace; outline: none; 
        }

        button { 
            padding: 12px 24px; background: #00f0ff; color: #000; border: none; 
            font-family: monospace; font-weight: bold; cursor: pointer; text-transform: uppercase; 
            transition: all 0.3s;
        }
        button:hover { background: #fff; box-shadow: 0 0 20px #00f0ff; transform: scale(1.02); }

        .canvas-container { position: relative; border: 1px solid #00f0ff; margin: 20px 0; line-height: 0; }
        
        /* CRT Scanline Effect */
        .canvas-container::after {
            content: " "; position: absolute; top: 0; left: 0; bottom: 0; right: 0;
            background: linear-gradient(rgba(18, 16, 16, 0) 50%, rgba(0, 0, 0, 0.15) 50%), 
                        linear-gradient(90deg, rgba(255, 0, 0, 0.03), rgba(0, 255, 0, 0.01), rgba(0, 0, 255, 0.03));
            background-size: 100% 4px, 3px 100%; pointer-events: none; z-index: 5;
        }

        #loader {
            position: absolute; top: 0; left: 0; right: 0; bottom: 0;
            background: rgba(0, 0, 0, 0.9); display: none;
            flex-direction: column; justify-content: center; align-items: center;
            z-index: 10;
        }

        canvas { background: #000; display: block; }
        .status { font-size: 11px; opacity: 0.6; margin-bottom: 30px; letter-spacing: 1px; }
        .blink { animation: blinker 1s linear infinite; }
        @keyframes blinker { 50% { opacity: 0; } }
    </style>
</head>
<body>

    <h1>> WORLD_GENERATOR_V2.5</h1>

    <div class="toolbar">
        <div class="control-group">
            <label>Random_Seed</label>
            <input type="number" id="seed" value="1">
        </div>
        <div class="control-group">
            <label>Projection</label>
            <select id="projection">
                <option value="mercator">RECTILINEAR</option>
                <option value="mollweide">MOLLWEIDE</option>
                <option value="sinusoidal">SINUSOIDAL</option>
            </select>
        </div>
        <div class="control-group">
            <label>Ice_Coverage %</label>
            <input type="number" id="ice" value="15" min="0" max="45">
        </div>
        <div class="control-group">
            <label>H2O_Sat %</label>
            <input type="number" id="water" value="70" min="0" max="100">
        </div>
        <div class="control-group">
            <label>Compute_Cycles</label>
            <input type="number" id="iterations" value="1500" min="100" max="5000">
        </div>
        <button onclick="generate()">[ EXECUTE ]</button>
    </div>

    <div class="canvas-container">
        <div id="loader">
            <div class="blink" style="color: #00f0ff;">SYSTEM_BUSY...</div>
            <div style="font-size: 10px; margin-top: 10px; color: #888;">MAPPING_FRACTAL_GEOMETRY</div>
        </div>
        <canvas id="worldCanvas"></canvas>
    </div>

    <div class="status" id="statusMsg">STATUS: STANDBY // API_READY</div>

<script>
/**
 * SHARED ENGINE MODULE
 * Contains all mathematical logic for deterministic generation
 */
const GeneratorEngine = {
    _seed: 1,
    
    // Deterministic PRNG
    seededRandom: function() {
        this._seed = (this._seed * 1664525 + 1013904223) % 4294967296;
        return this._seed / 4294967296;
    },

    // Core heightmap generation
    generateData: function(s) {
        this._seed = s.seed;
        let map = new Float32Array(s.width * s.height).fill(0);

        for(let i = 0; i < s.iterations; i++) {
            let phi = this.seededRandom() * Math.PI * 2;
            let theta = this.seededRandom() * Math.PI;
            let nx = Math.cos(phi) * Math.sin(theta);
            let ny = Math.sin(phi) * Math.sin(theta);
            let nz = Math.cos(theta);

            for(let y = 0; y < s.height; y++) {
                let lat = (Math.PI * y) / s.height - Math.PI/2;
                let cosL = Math.cos(lat), sinL = Math.sin(lat);
                for(let x = 0; x < s.width; x++) {
                    let lon = (Math.PI * 2 * x) / s.width - Math.PI;
                    let px = Math.cos(lon) * cosL, py = Math.sin(lon) * cosL, pz = sinL;
                    map[y * s.width + x] += (px * nx + py * ny + pz * nz > 0) ? 1 : -1;
                }
            }
        }
        let sorted = [...map].sort((a,b) => a-b);
        return {
            map,
            seaLevel: sorted[Math.floor((s.waterPct/100) * (sorted.length-1))],
            min: sorted[0],
            max: sorted[sorted.length-1]
        };
    }
};

/**
 * UI & RENDERING LOGIC
 */
const canvas = document.getElementById('worldCanvas');
const ctx = canvas.getContext('2d');
const loader = document.getElementById('loader');
const W = 1000, H = 500;
canvas.width = W; canvas.height = H;

async function generate() {
    // 1. UI Feedback
    loader.style.display = 'flex';
    document.getElementById('statusMsg').innerText = "STATUS: PROCESSING // SEED " + document.getElementById('seed').value;
    await new Promise(r => setTimeout(r, 100)); // Allow DOM update

    // 2. Fetch Settings
    const settings = {
        width: W, height: H,
        seed: parseInt(document.getElementById('seed').value),
        iterations: parseInt(document.getElementById('iterations').value),
        waterPct: parseInt(document.getElementById('water').value),
        icePct: parseInt(document.getElementById('ice').value) / 100,
        proj: document.getElementById('projection').value
    };

    // 3. Run Engine
    const { map, seaLevel, min, max } = GeneratorEngine.generateData(settings);

    // 4. Render Pass
    const imgData = ctx.createImageData(W, H);
    for(let y=0; y<H; y++) {
        let latNorm = y / H;
        let latRad = latNorm * Math.PI - Math.PI/2;

        for(let x=0; x<W; x++) {
            let h = map[y*W + x];
            let lonRad = (x / W) * 2 * Math.PI - Math.PI;

            // Noisy Dual-Pole Ice logic
            let distFromPole = latNorm < 0.5 ? latNorm : 1 - latNorm;
            let iceNoise = (h / (max || 1)) * 0.04;
            let isIce = distFromPole < (settings.icePct / 2) + iceNoise + (Math.max(0, (h-seaLevel)/(max-seaLevel)) * 0.05);

            // Handle Projections
            let tx = x, skip = false;
            if(settings.proj === 'mollweide') {
                tx = W/2 + (W/2) * (lonRad/Math.PI) * Math.cos(latRad);
                if(Math.abs(x - W/2) > (W/2) * Math.cos(latRad)) skip = true;
            } else if(settings.proj === 'sinusoidal') {
                tx = W/2 + (x - W/2) * Math.cos(latRad);
                if(Math.abs(x - W/2) > (W/2) * Math.cos(latRad)) skip = true;
            }
            if(skip) continue;

            let outIdx = (y*W + Math.floor(tx)) * 4;
            let r, g, b;

            if (isIce) {
                let d = (h - min) / (max - min);
                r = 190 + (d * 65); g = 210 + (d * 45); b = 255;
            } else if(h <= seaLevel) {
                let d = (h - min) / (seaLevel - min);
                r = 0; g = 30 * d; b = 60 + (d * 120);
            } else {
                let d = (h - seaLevel) / (max - seaLevel);
                r = 15 + (d * 180); g = 100 - (d * 30); b = 30;
            }

            imgData.data[outIdx] = r; imgData.data[outIdx+1] = g;
            imgData.data[outIdx+2] = b; imgData.data[outIdx+3] = 255;
        }
    }
    ctx.clearRect(0,0,W,H);
    ctx.putImageData(imgData, 0, 0);
    
    loader.style.display = 'none';
    document.getElementById('statusMsg').innerText = "STATUS: STABLE // RENDER_COMPLETE";
}

// --- URL PARAMETER API OVERRIDE ---
// Allows functionality like: index.html?seed=123&water=50
window.onload = () => {
    const params = new URLSearchParams(window.location.search);
    if(params.has('seed')) document.getElementById('seed').value = params.get('seed');
    if(params.has('water')) document.getElementById('water').value = params.get('water');
    if(params.has('ice')) document.getElementById('ice').value = params.get('ice');
    if(params.has('cycles')) document.getElementById('iterations').value = params.get('cycles');
    
    // If seed is provided in URL, auto-generate. Otherwise, wait for manual click.
    if(window.location.search.length > 0) {
        generate();
    }
};

// generate(); // Auto-run disabled by default
</script>
</body>
</html>
