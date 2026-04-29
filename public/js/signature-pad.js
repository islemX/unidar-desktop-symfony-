class UnidarSignaturePad {
    constructor(containerId, options = {}) {
        this.container = document.getElementById(containerId);
        if (!this.container) return;

        this.width = options.width || 400;
        this.height = options.height || 150;
        this.color = options.color || '#000000';
        this.background = options.backgroundColor || '#ffffff';

        this.init();
    }

    init() {
        this.container.innerHTML = `
            <div class="signature-pad-wrapper" style="position: relative; border: 1px solid var(--color-surface-200); border-radius: var(--radius-md); background: ${this.background}; overflow: hidden;">
                <canvas width="${this.width}" height="${this.height}" style="display: block; cursor: crosshair; width: 100%; height: ${this.height}px;"></canvas>
                <button type="button" class="btn btn-secondary btn-sm" style="position: absolute; bottom: 8px; right: 8px; padding: 4px 8px; font-size: 10px;" onclick="window.unidarSignaturePad.clear()">Clear</button>
            </div>
        `;

        this.canvas = this.container.querySelector('canvas');
        this.ctx = this.canvas.getContext('2d');
        this.isDrawing = false;
        this.isEmptyFlag = true;

        // Make it global for the clear button
        window.unidarSignaturePad = this;

        this.setupEvents();
    }

    setupEvents() {
        const getPos = (e) => {
            const rect = this.canvas.getBoundingClientRect();
            const clientX = e.touches ? e.touches[0].clientX : e.clientX;
            const clientY = e.touches ? e.touches[0].clientY : e.clientY;
            return {
                x: (clientX - rect.left) * (this.canvas.width / rect.width),
                y: (clientY - rect.top) * (this.canvas.height / rect.height)
            };
        };

        const start = (e) => {
            e.preventDefault();
            this.isDrawing = true;
            this.isEmptyFlag = false;
            const pos = getPos(e);
            this.ctx.beginPath();
            this.ctx.moveTo(pos.x, pos.y);
            this.ctx.strokeStyle = this.color;
            this.ctx.lineWidth = 2;
            this.ctx.lineCap = 'round';
            this.ctx.lineJoin = 'round';
        };

        const move = (e) => {
            if (!this.isDrawing) return;
            e.preventDefault();
            const pos = getPos(e);
            this.ctx.lineTo(pos.x, pos.y);
            this.ctx.stroke();
        };

        const stop = () => {
            this.isDrawing = false;
        };

        this.canvas.addEventListener('mousedown', start);
        this.canvas.addEventListener('mousemove', move);
        window.addEventListener('mouseup', stop);

        this.canvas.addEventListener('touchstart', start, { passive: false });
        this.canvas.addEventListener('touchmove', move, { passive: false });
        this.canvas.addEventListener('touchend', stop);
    }

    clear() {
        this.ctx.clearRect(0, 0, this.canvas.width, this.canvas.height);
        this.isEmptyFlag = true;
    }

    toDataURL() {
        return this.canvas.toDataURL();
    }

    isEmpty() {
        return this.isEmptyFlag;
    }

    isValid() {
        return !this.isEmptyFlag;
    }
}

// Explicitly expose to window to avoid scope issues
window.UnidarSignaturePad = UnidarSignaturePad;
console.log('UnidarSignaturePad loaded and registered.');
