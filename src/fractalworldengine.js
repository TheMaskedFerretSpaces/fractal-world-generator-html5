/**
 * FractalWorldEngine
 * Extracted from original project logic.
 */
class FractalWorldEngine {
    constructor(size, roughness, seed) {
        this.size = size;
        this.roughness = roughness;
        this.seed = seed;
        this.grid = this.generateEmptyGrid(size);
    }

    generateEmptyGrid(size) {
        let matrix = new Array(size);
        for (let i = 0; i < size; i++) {
            matrix[i] = new Float32Array(size);
        }
        return matrix;
    }

    // This is the core logic moved from the original <script> tag
    computeFractal() {
        // Logic for setting corners based on seed
        this.grid[0][0] = this.grid[0][this.size - 1] = this.seed;
        this.grid[this.size - 1][0] = this.grid[this.size - 1][this.size - 1] = this.seed;

        this.divide(this.size - 1);
        return this.grid;
    }

    divide(size) {
        let x, y, half = size / 2;
        let scale = this.roughness * size;
        if (half < 1) return;

        for (y = half; y < this.size - 1; y += size) {
            for (x = half; x < this.size - 1; x += size) {
                this.square(x, y, half, Math.random() * scale * 2 - scale);
            }
        }
        for (y = 0; y <= this.size - 1; y += half) {
            for (x = (y + half) % size; x <= this.size - 1; x += size) {
                this.diamond(x, y, half, Math.random() * scale * 2 - scale);
            }
        }
        this.divide(size / 2);
    }

    // Helper methods for calculation
    square(x, y, size, offset) {
        let ave = this.average([
            this.grid[x - size][y - size],
            this.grid[x + size][y - size],
            this.grid[x - size][y + size],
            this.grid[x + size][y + size]
        ]);
        this.grid[x][y] = ave + offset;
    }

    diamond(x, y, size, offset) {
        let ave = this.average([
            this.grid[x][y - size],
            this.grid[x + size][y],
            this.grid[x][y + size],
            this.grid[x - size][y]
        ]);
        this.grid[x][y] = ave + offset;
    }

    average(values) {
        let valid = values.filter(v => v !== undefined);
        return valid.reduce((a, b) => a + b) / valid.length;
    }
}