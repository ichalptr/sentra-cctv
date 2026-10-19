import { MotionDetector } from './motion-detector.js?v=motion-20261016-1';

export function motionMarkup() {
    return `<details class="motion-test"><summary>Uji deteksi gerakan</summary>
        <p class="help-text">Analisis perubahan frame di browser · bukan Frigate atau deteksi orang/kendaraan. Hanya berjalan selama rekaman diputar. Hasil uji hilang saat pemutar ditutup, kecuali disimpan sebagai catatan.</p>
        <div class="motion-controls"><label>Sensitivitas<select data-motion-sensitivity><option value="0.04">Rendah</option><option value="0.015" selected>Normal</option><option value="0.005">Tinggi</option></select></label>
        <label>Area analisis<select data-motion-area><option value="full">Seluruh gambar</option><option value="center">Bagian tengah (60%)</option></select></label>
        <button class="secondary" data-motion-start>Mulai uji</button><button class="secondary" data-motion-stop disabled>Hentikan</button></div>
        <p class="motion-status" data-motion-status role="status">Belum dijalankan. Mulai dari posisi rekaman saat ini.</p>
        <p class="help-text" data-motion-progress>0 frame dianalisis. Hasil hanya mencakup bagian yang diputar selama uji.</p>
        <div class="motion-results" data-motion-results><p class="help-text">Belum ada hasil uji.</p></div>
        <p class="help-text">Gerakan bukan berarti ancaman. Bayangan, cahaya, dan pergerakan kamera dapat memicu hasil. Gunakan kamera tetap; periksa rekaman sebelum menindaklanjuti.</p>
    </details>`;
}

export function attachMotionTest({ video, container, signal, formatTime, onSave }) {
    const find = selector => container.querySelector(selector);
    const startButton = find('[data-motion-start]'), stopButton = find('[data-motion-stop]');
    const sensitivity = find('[data-motion-sensitivity]'), area = find('[data-motion-area]');
    const status = find('[data-motion-status]'), progress = find('[data-motion-progress]');
    const results = find('[data-motion-results]');
    const canvas = document.createElement('canvas');
    canvas.width = 192; canvas.height = 108;
    const context = canvas.getContext('2d', { willReadFrequently: true });
    let detector, running = false, frameHandle, lastSample = -Infinity, frames = 0, firstTime = null, lastTime = null;
    let runSettings = '', renderedCount = -1;
    const saved = new Set(), saving = new Set();
    const supportsVideoFrames = typeof video.requestVideoFrameCallback === 'function';

    function renderResults() {
        if (!detector || detector.events.length === renderedCount) return;
        renderedCount = detector.events.length;
        results.innerHTML = '';
        if (!renderedCount) {
            const empty = document.createElement('p'); empty.className = 'help-text';
            empty.textContent = 'Belum ada rentang gerakan terkonfirmasi. Hasil muncul setelah gerakan berhenti atau uji dihentikan.';
            results.append(empty); return;
        }
        detector.events.forEach((event, index) => {
            const row = document.createElement('div'); row.className = 'motion-result';
            const text = document.createElement('span');
            text.textContent = `${formatTime(event.start)}–${formatTime(event.end)} · gerakan uji`;
            const actions = document.createElement('div');
            const replay = document.createElement('button'); replay.className = 'table-action'; replay.textContent = 'Lihat';
            replay.addEventListener('click', () => {
                stop('Uji dihentikan untuk meninjau hasil.');
                video.currentTime = Math.max(0, event.start - 1);
                video.play().catch(() => { status.textContent = 'Tekan putar untuk meninjau rekaman.'; });
            }, { signal });
            const save = document.createElement('button'); save.className = 'table-action';
            save.textContent = saved.has(index) ? 'Tersimpan' : saving.has(index) ? 'Menyimpan…' : 'Simpan catatan';
            save.disabled = saved.has(index) || saving.has(index);
            save.addEventListener('click', async () => {
                saving.add(index); save.disabled = true; save.textContent = 'Menyimpan…';
                try {
                    await onSave({ ...event, settings: runSettings });
                    saved.add(index); save.textContent = 'Tersimpan';
                } catch (error) {
                    status.textContent = error.message; save.textContent = 'Coba simpan lagi'; save.disabled = false;
                } finally { saving.delete(index); }
            }, { signal });
            actions.append(replay, save); row.append(text, actions); results.prepend(row);
        });
    }

    function cancelFrame() {
        if (frameHandle !== undefined) {
            if (supportsVideoFrames) video.cancelVideoFrameCallback(frameHandle);
            else cancelAnimationFrame(frameHandle);
            frameHandle = undefined;
        }
    }
    function stop(message = 'Uji dihentikan. Hasil belum disimpan otomatis.') {
        running = false; cancelFrame(); detector?.finish(); renderResults();
        startButton.disabled = false; stopButton.disabled = true; sensitivity.disabled = false; area.disabled = false;
        status.textContent = message;
    }
    function schedule() {
        if (!running || signal.aborted) return;
        frameHandle = supportsVideoFrames ? video.requestVideoFrameCallback(analyze) : requestAnimationFrame(analyze);
    }
    function analyze() {
        frameHandle = undefined;
        if (!running || signal.aborted) return;
        if (!video.paused && !video.seeking && video.readyState >= 2 && video.currentTime - lastSample >= 0.24) {
            const second = video.currentTime;
            try {
                const crop = area.value === 'center' ? 0.2 : 0;
                context.drawImage(video, video.videoWidth * crop, video.videoHeight * crop,
                    video.videoWidth * (1 - 2 * crop), video.videoHeight * (1 - 2 * crop), 0, 0, canvas.width, canvas.height);
                const sample = detector.sample(context.getImageData(0, 0, canvas.width, canvas.height).data, second);
                lastSample = second; frames++; firstTime ??= second; lastTime = second;
                status.textContent = sample.lighting ? 'Perubahan cahaya dominan · frame dilewati.' :
                    `${sample.confirmed ? 'Gerakan terkonfirmasi' : sample.moving ? 'Memeriksa gerakan…' : 'Memantau perubahan frame'} · ${(sample.ratio * 100).toFixed(1)}% piksel berubah`;
                progress.textContent = `${frames} frame dianalisis · posisi ${formatTime(firstTime)} sampai ${formatTime(lastTime)} (dapat terputus karena jeda/seek).`;
                renderResults();
                if (detector.events.length >= 100) { stop('Batas 100 rentang uji tercapai. Simpan hasil yang diperlukan.'); return; }
            } catch {
                stop('Frame video tidak dapat dianalisis. Gunakan rekaman lokal yang dapat diputar.'); return;
            }
        }
        schedule();
    }
    startButton.addEventListener('click', async () => {
        if (saving.size) { status.textContent = 'Tunggu hingga penyimpanan catatan selesai.'; return; }
        if (!context || video.error || video.readyState < 2 || !video.videoWidth) {
            status.textContent = 'Tunggu hingga video siap diputar, lalu mulai uji.'; return;
        }
        if (detector?.events.length && !window.confirm('Mulai uji baru akan mengganti hasil uji yang belum disimpan. Lanjutkan?')) return;
        detector = new MotionDetector({ ratio: Number(sensitivity.value) });
        saved.clear(); saving.clear(); frames = 0; firstTime = null; lastTime = null; lastSample = -Infinity; renderedCount = -1;
        runSettings = `Sensitivitas ${sensitivity.selectedOptions[0].textContent}; area ${area.selectedOptions[0].textContent}`;
        running = true; startButton.disabled = true; stopButton.disabled = false; sensitivity.disabled = true; area.disabled = true;
        status.textContent = 'Menyiapkan analisis frame…'; renderResults();
        if (video.ended) video.currentTime = 0;
        video.loop = false;
        const loopControl = document.querySelector('#playback-loop'); if (loopControl) loopControl.checked = false;
        try { await video.play(); if (!signal.aborted && running) schedule(); }
        catch { stop('Tekan putar pada video, lalu mulai uji kembali.'); }
    }, { signal });
    stopButton.addEventListener('click', () => stop(), { signal });
    video.addEventListener('pause', () => {
        if (!running) return;
        detector.breakSequence(); lastSample = -Infinity; renderResults(); status.textContent = 'Uji dijeda. Putar rekaman untuk melanjutkan.';
    }, { signal });
    video.addEventListener('seeking', () => {
        if (!running) return;
        detector.breakSequence(); lastSample = -Infinity; renderResults();
    }, { signal });
    video.addEventListener('ended', () => { if (running) stop('Rekaman selesai. Periksa hasil uji di bawah.'); }, { signal });
    video.addEventListener('error', () => { if (running) stop('Video gagal dimuat. Uji dihentikan.'); }, { signal });
    signal.addEventListener('abort', () => { running = false; cancelFrame(); }, { once: true });
}
