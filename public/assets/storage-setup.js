export function initializeStorageSetup({ boot, api, esc, toast, preview, play }) {
    const find = selector => document.querySelector(selector);
    const form = find('#storage-setup-form'), cameraForm = find('#camera-setup-form');
    let state = boot.storage;
    const formatBytes = size => size > 1048576 ? (size / 1048576).toFixed(1) + ' MB' : Math.ceil(size / 1024) + ' KB';
    const formatDate = value => {if(!value)return 'Belum pernah'; const date=new Date(value.includes('T')?value:value.replace(' ','T')+'Z');return Number.isNaN(date.getTime())?'Waktu tidak tersedia':new Intl.DateTimeFormat('id-ID',{timeZone:'Asia/Jakarta',day:'2-digit',month:'short',year:'numeric',hour:'2-digit',minute:'2-digit'}).format(date)+' WIB'};
    function render() {
        const settings = state.settings;
        find('#run-backup').disabled = !settings.configured;
        find('#storage-summary').textContent = !settings.configured ? 'Belum diatur. Mulai dari Pengaturan → langkah 2 dan 3.' :
            `${settings.source === 'demo' ? 'Sumber: video uji bawaan' : 'Sumber: folder rekaman'} · ${settings.destination === 'test' ? 'Tujuan: uji lokal pada komputer yang sama' : 'Tujuan: folder pilihan (pastikan media terpisah)'} · ${state.archives.length} salinan tercatat`;
        const run = state.lastRun;
        find('#backup-result').textContent = !run ? 'Belum ada backup yang dijalankan.' :
            `${({ success: 'Selesai', pending: 'Masih ada file tertunda', failed: 'Ada kegagalan' })[run.status] || run.status} · ${run.copied} file baru disalin · ${run.verified} salinan lama diperiksa · ${run.pending} tertunda · ${run.expired} kedaluwarsa. Terakhir: ${formatDate(run.finishedAt || run.at)}${run.errors?.length ? '\n' + run.errors.join('\n') : ''}`;
        find('#scheduler-state').textContent = `${settings.automatic ? 'Jadwal otomatis diaktifkan' : 'Jadwal otomatis belum diaktifkan'} · ${state.schedulerActive ? 'Penjadwal aktif (heartbeat terbaru)' : 'Penjadwal belum terdeteksi / heartbeat kedaluwarsa'}. ${settings.automatic && !state.schedulerActive ? 'Jadwal belum dapat diandalkan sampai penjadwal aktif.' : ''} File yang masih baru ditunda minimal 60 detik; maksimal 10 salinan baru per putaran.`;
        find('#archive-list').innerHTML = state.archives.map((archive, index) => `<article class="archive-row"><div><h3>${esc(archive.name)}</h3><p>${formatBytes(archive.bytes)} · terakhir cocok saat backup: ${esc(formatDate(archive.verifiedAt))}</p><p>${archive.protected ? 'Dipertahankan karena memiliki catatan kejadian' : 'Mengikuti retensi backup'} · uji pemulihan: ${esc(formatDate(archive.restoreTestedAt))}</p></div><div class="archive-actions"><button class="table-action" data-archive-play="${index}">Putar salinan</button><button class="table-action" data-archive-restore="${index}">Uji pemulihan</button></div></article>`).join('') || '<p class="empty">Belum ada salinan. Atur folder, lalu jalankan Backup sekarang.</p>';
        document.querySelectorAll('[data-archive-play]').forEach(button => button.onclick = () => play(state.archives[Number(button.dataset.archivePlay)]));
        document.querySelectorAll('[data-archive-restore]').forEach(button => button.onclick = async () => {
            const archive = state.archives[Number(button.dataset.archiveRestore)];
            button.disabled = true; button.textContent = 'Memulihkan…';
            try {
                const result = await api(boot.urls.restoreTest.replace('__ID__', archive.id), 'POST', {});
                toast(result.message); find('#backup-result').textContent = result.message; await refresh(false);
            } catch (error) { toast(error.message); button.disabled = false; button.textContent = 'Coba lagi'; }
        });
    }
    async function refresh(reportError = true) {
        try {
            const response = await fetch(boot.urls.storageStatus, { credentials: 'same-origin', headers: { Accept: 'application/json' }, cache: 'no-store' });
            if (!response.ok) throw Error('Status tidak dapat dimuat. Periksa koneksi dan sesi login.');
            state = await response.json(); render();
        } catch (error) { if (reportError) toast(error.message); }
    }
    for (const [key, value] of Object.entries(state.settings)) {
        const input = form.elements.namedItem(key);
        if (input) { if (input.type === 'checkbox') input.checked = !!value; else input.value = value ?? ''; }
    }
    for (const [key, value] of Object.entries(boot.cameraSetup)) {
        const input = cameraForm.elements.namedItem(key);
        if (input) input.value = key === 'enabled' ? (value ? '1' : '0') : value ?? '';
    }
    function fields() {
        find('#daily-schedule-field').hidden = form.elements.interval.value !== '1440';
        find('#source-folder-fields').hidden = form.elements.source.value !== 'folder';
        find('#destination-folder-field').hidden = form.elements.destination.value !== 'folder';
        form.elements.camera_name.required = form.elements.source.value === 'folder';
        cameraForm.elements.name.required = cameraForm.elements.enabled.value === '1';
        form.elements.source_path.required = form.elements.source.value === 'folder';
        form.elements.destination_path.required = form.elements.destination.value === 'folder';
        find('#camera-address-fields').hidden = cameraForm.elements.enabled.value !== '1';
        cameraForm.elements.player_url.required = cameraForm.elements.enabled.value === '1';
    }
    form.elements.source.onchange = fields; form.elements.destination.onchange = fields; cameraForm.elements.enabled.onchange = fields;
    form.elements.interval.onchange = fields;
    fields(); render();
    cameraForm.onsubmit = async event => {
        event.preventDefault(); const button = cameraForm.querySelector('button.primary'); button.disabled = true;
        try {
            const data = Object.fromEntries(new FormData(cameraForm)); data.enabled = data.enabled === '1'; data.name = data.name || 'Kamera 1';
            await api(boot.urls.cameraSetup, 'PUT', data); location.hash = 'live'; location.reload();
        } catch (error) { find('#camera-setup-result').textContent = error.message; button.disabled = false; }
    };
    find('#preview-gateway').onclick = () => {
        try {
            const url = new URL(cameraForm.elements.player_url.value);
            if (!['http:', 'https:'].includes(url.protocol) || url.username || url.password || url.search || url.hash || /\.(m3u8|mp4|sdp)\/?$/i.test(url.pathname)) throw Error();
            preview(url.href, cameraForm.elements.name.value);
        } catch { toast('Isi alamat halaman player HTTP/HTTPS tanpa password atau token. Alamat RTSP/playlist memerlukan gateway.'); }
    };
    form.onsubmit = async event => {
        event.preventDefault(); const button = form.querySelector('button.primary'); button.disabled = true;
        find('#storage-setup-result').textContent = 'Memeriksa folder dan izin tulis…';
        try {
            const data = Object.fromEntries(new FormData(form)); data.automatic = form.elements.automatic.checked; data.camera_name = data.camera_name || 'Kamera 1';
            await api(boot.urls.storageConfigure, 'PUT', data); location.hash = 'backup'; location.reload();
        } catch (error) { find('#storage-setup-result').textContent = error.message; button.disabled = false; }
    };
    find('#run-backup').onclick = async () => {
        const button = find('#run-backup'); button.disabled = true; button.textContent = 'Menyalin & memeriksa…';
        find('#backup-result').textContent = 'Backup berjalan. Tunggu hasil pemeriksaan file; jangan tutup halaman ini.';
        try {
            const result = await api(boot.urls.storageRun, 'POST', {}); state = result.storage; render();
        } catch (error) { find('#backup-result').textContent = error.message; }
        finally { button.disabled = false; button.textContent = 'Backup sekarang'; }
    };
    find('#refresh-backup').onclick = () => refresh();
    window.addEventListener('hashchange', () => { if (location.hash === '#backup') refresh(); });
}
