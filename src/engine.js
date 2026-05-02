// engine.js - The Core Logic
const GeneratorEngine = {
    _seed: 1,
    seededRandom: function() {
        this._seed = (this._seed * 1664525 + 1013904223) % 4294967296;
        return this._seed / 4294967296;
    },

    generateData: function(settings) {
        const { width, height, seed, iterations, waterPct, icePct } = settings;
        this._seed = seed;
        let map = new Float32Array(width * height).fill(0);

        // 1. Faulting Logic
        for (let i = 0; i < iterations; i++) {
            let phi = this.seededRandom() * Math.PI * 2;
            let theta = this.seededRandom() * Math.PI;
            let nx = Math.cos(phi) * Math.sin(theta);
            let ny = Math.sin(phi) * Math.sin(theta);
            let nz = Math.cos(theta);

            for (let y = 0; y < height; y++) {
                let lat = (Math.PI * y) / height - Math.PI / 2;
                let cosL = Math.cos(lat), sinL = Math.sin(lat);
                for (let x = 0; x < width; x++) {
                    let lon = (Math.PI * 2 * x) / width - Math.PI;
                    let px = Math.cos(lon) * cosL, py = Math.sin(lon) * cosL, pz = sinL;
                    map[y * width + x] += (px * nx + py * ny + pz * nz > 0) ? 1 : -1;
                }
            }
        }

        // 2. Statistics for coloring
        let sorted = [...map].sort((a, b) => a - b);
        let seaLevel = sorted[Math.floor((waterPct / 100) * (sorted.length - 1))];
        return { map, seaLevel, min: sorted[0], max: sorted[sorted.length - 1] };
    }
};

if (typeof module !== 'undefined') module.exports = GeneratorEngine;
