// Local frame-difference trial. This does not classify objects or use Frigate.
export class MotionDetector {
    constructor({ ratio = 0.015, pixelThreshold = 24, minSamples = 3, quietSeconds = 1.5 } = {}) {
        this.options = { ratio, pixelThreshold, minSamples, quietSeconds };
        this.events = [];
        this.previous = null;
        this.lastTime = null;
        this.pending = null;
        this.active = null;
    }

    breakSequence() {
        this.finish();
        this.previous = null;
        this.lastTime = null;
        this.pending = null;
    }

    finish() {
        if (this.active) {
            this.events.push({ ...this.active });
            this.active = null;
        }
        this.pending = null;
    }

    sample(rgba, second) {
        if (!Number.isFinite(second) || second < 0 || !rgba.length || rgba.length % 4) {
            throw new Error('Frame atau posisi rekaman tidak valid.');
        }
        const pixels = rgba.length / 4;
        const gray = new Uint8Array(pixels);
        for (let i = 0; i < pixels; i++) {
            gray[i] = (rgba[i * 4] * 77 + rgba[i * 4 + 1] * 150 + rgba[i * 4 + 2] * 29) >> 8;
        }
        if (this.lastTime !== null && (second <= this.lastTime || second - this.lastTime > 5)) {
            this.breakSequence();
        }
        let changed = 0, brightness = 0;
        const comparable = this.previous?.length === pixels;
        if (comparable) {
            for (let i = 0; i < pixels; i++) {
                const difference = gray[i] - this.previous[i];
                brightness += difference;
                if (Math.abs(difference) >= this.options.pixelThreshold) changed++;
            }
        }
        this.previous = gray;
        this.lastTime = second;
        const ratio = changed / pixels;
        const lighting = comparable && ratio > 0.65 && Math.abs(brightness / pixels) > 14;
        const moving = comparable && !lighting && ratio >= this.options.ratio;
        if (moving) {
            this.pending ??= { start: second, count: 0 };
            this.pending.count++;
            if (!this.active && this.pending.count >= this.options.minSamples) {
                this.active = { start: this.pending.start, end: second, peak: ratio };
            }
            if (this.active) {
                this.active.end = second;
                this.active.peak = Math.max(this.active.peak, ratio);
            }
        } else {
            this.pending = null;
            if (this.active && second - this.active.end >= this.options.quietSeconds) this.finish();
        }
        return { ratio, moving, lighting, confirmed: !!this.active };
    }
}
