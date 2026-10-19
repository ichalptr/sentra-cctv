import { initializeStorageSetup } from './storage-setup.js?v=storage-20261017-1';
const $=s=>document.querySelector(s), $$=s=>[...document.querySelectorAll(s)];
const esc=s=>String(s??'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
function read(k,fallback){return window.SENTRA_BOOT[k]??fallback}
async function api(url,method,data){const response=await fetch(url,{method,credentials:'same-origin',headers:{'Content-Type':'application/json','Accept':'application/json','X-CSRF-TOKEN':document.querySelector('meta[name="csrf-token"]').content},body:JSON.stringify(data)});let result={};try{result=await response.json()}catch{}if(!response.ok){if(response.status===401||response.status===419)throw Error('Sesi berakhir. Muat ulang halaman dan masuk kembali.');throw Error(result.errors?Object.values(result.errors).flat().join(' '):(result.message||'Penyimpanan gagal. Coba lagi.'))}return result}
const cameras=window.SENTRA_BOOT.cameras;
const publicCamera=read('publicCamera',null);
const gatewayCamera=read('cameraSetup',{});
function timecode(seconds){const n=Math.max(0,Math.floor(Number(seconds)||0));return `${String(Math.floor(n/60)).padStart(2,'0')}:${String(n%60).padStart(2,'0')}`}
function clipDuration(c){return timecode(c.seconds)}
let filter='all',query='';const today=new Intl.DateTimeFormat('en-CA',{timeZone:'Asia/Jakarta',year:'numeric',month:'2-digit',day:'2-digit'}).format(new Date());
let incidents=read('incidents',[]);
const records=read('records',[]);
function archiveLabel(r){if(r?.timeBasis==='file')return `${r.date} · ${r.start} · waktu perubahan file server`;return r?.sourceTime?`${r.date} · ${r.start} · waktu sumber (zona tidak diketahui)`:`${r?.date||today} · ${r?.start||'08.00'} WIB · contoh`}
$('#record-camera').innerHTML='<option value="all">Semua kamera</option>'+cameras.filter(c=>c.online).map(c=>`<option>${esc(c.name)}</option>`).join('');
const titles={live:['Kamera','Kamera','Pilih kamera untuk melihat rekaman atau menandai kejadian.'],recordings:['Rekaman','Rekaman','Cari kamera dan tanggal, lalu putar rekamannya.'],backup:['Backup','Backup','Salin, verifikasi, dan uji pemulihan rekaman.'],incidents:['Catatan kejadian','Catatan kejadian','Lihat kejadian yang sudah dicatat dan buka rekaman terkait.'],settings:['Pengaturan','Pengaturan','Ikuti tiga langkah untuk menyiapkan kamera dan backup.']};
let activePage;
function page(p){if(!titles[p])p='live';$$('.page').forEach(x=>x.hidden=x.id!==p+'-page');$$('nav button').forEach(x=>x.classList.toggle('active',x.dataset.page===p));$('#breadcrumb-title').textContent=titles[p][0];$('#page-title').textContent=titles[p][1];$('#page-subtitle').textContent=titles[p][2];location.hash=p;updateGridPlayback();if(activePage!==p){window.scrollTo({top:0,behavior:"instant"})}activePage=p}
$$('[data-page]').forEach(b=>b.addEventListener('click',()=>page(b.dataset.page)));window.addEventListener('hashchange',()=>page(location.hash.slice(1)));
function tick(){$('#clock').textContent=new Intl.DateTimeFormat('id-ID',{timeZone:'Asia/Jakarta',hour:'2-digit',minute:'2-digit',second:'2-digit',hour12:false}).format(new Date())+' WIB'}tick();setInterval(tick,1000);
function toast(s){$('#toast').textContent=s;$('#toast').classList.add('show');clearTimeout(window.toastTimer);window.toastTimer=setTimeout(()=>$('#toast').classList.remove('show'),4500)}
function stopGridVideo(video){video.pause();if(video.hasAttribute('src')){video.dataset.videoSrc=video.getAttribute('src');video.removeAttribute('src');video.load()}}
function stopGatewayPreview(){ $$('.gateway-preview iframe').forEach(frame=>frame.removeAttribute('src')) }
function updateGatewayPreview(){ $$('.gateway-preview iframe').forEach(frame=>{if(location.hash==='#live'&&!document.hidden&&!$('#modal').open){if(!frame.hasAttribute('src'))frame.src=frame.dataset.src}else frame.removeAttribute('src')}) }
function gatewayUrl(){const url=new URL(gatewayCamera.player_url);url.searchParams.set('autoplay','true');url.searchParams.set('muted','true');url.searchParams.set('controls','false');return url.href}
function openGateway(url=gatewayCamera.player_url,name=gatewayCamera.name){modal('Tayangan kamera · '+name,`<div class="public-player-frame"><iframe src="${esc(url)}" title="Tayangan gateway ${esc(name)}" allow="autoplay; fullscreen" sandbox="allow-scripts allow-same-origin allow-presentation" referrerpolicy="no-referrer" allowfullscreen></iframe></div><p class="help-text">Pastikan gambar bergerak dan waktu kamera sesuai. Halaman terbuka belum membuktikan siaran berjalan. Jika kosong, periksa jaringan, login gateway, izin embed, dan kecocokan HTTP/HTTPS.</p><p class="help-text">Tayangan ini tidak direkam oleh Sentra. File rekaman dari NVR/gateway diatur terpisah pada langkah 2.</p>`)}
function pauseVideos(){ $$('video').forEach(v=>{if(v.classList.contains('grid-video'))stopGridVideo(v);else v.pause()});stopPublicPreview();stopGatewayPreview() }
function updateGridPlayback(){ $$('.grid-video').forEach(v=>{if(location.hash==='#live'&&!document.hidden&&!$('#modal').open){if(!v.hasAttribute('src')&&v.dataset.videoSrc)v.src=v.dataset.videoSrc;v.play().catch(()=>{})}else stopGridVideo(v)});updatePublicPreview();updateGatewayPreview() }
let playerEvents;
function modal(title,body){playerEvents?.abort();pauseVideos();$('#modal-title').textContent=title;$('#modal-body').innerHTML=body;if(!$('#modal').open)$('#modal').showModal()}$('#close-modal').onclick=()=>$('#modal').close();$('#modal').addEventListener('close',()=>{playerEvents?.abort();pauseVideos();$('#modal-body').innerHTML='';updateGridPlayback()});document.addEventListener('visibilitychange',()=>{if(document.hidden)pauseVideos();else updateGridPlayback()});$('#modal').addEventListener('click',e=>{if(e.target===$('#modal')){const r=$('#modal').getBoundingClientRect();if(e.clientX<r.left||e.clientX>r.right||e.clientY<r.top||e.clientY>r.bottom)$('#modal').close()}});
function renderCameras(){
const items=cameras.filter(c=>(filter==='all'||(filter==='available'&&c.online)||(filter==='offline'&&!c.online))&&`${c.name} ${c.zone}`.toLowerCase().includes(query));
const showPublic=publicCamera&&(filter==='all'||filter==='public')&&`${publicCamera.name} ${publicCamera.zone}`.toLowerCase().includes(query);
const gatewayCard=gatewayCamera.enabled&&(filter==='all'||filter==='gateway')&&`${gatewayCamera.name} ${gatewayCamera.location}`.toLowerCase().includes(query)?`<article class="camera-card"><div class="camera-preview gateway-preview"><iframe data-src="${esc(gatewayUrl())}" title="Tayangan gateway ${esc(gatewayCamera.name)}" allow="autoplay; fullscreen" sandbox="allow-scripts allow-same-origin allow-presentation" referrerpolicy="no-referrer" tabindex="-1"></iframe><span class="sample-watermark">Player kamera</span><button class="public-preview-open" data-gateway><span>Perbesar ↗</span></button></div><div class="cam-info"><div><h3>${esc(gatewayCamera.name)}</h3><p>${esc(gatewayCamera.location||'Gateway perusahaan')} · periksa tayangan</p></div><button data-gateway aria-label="Perbesar kamera">↗</button></div></article>`:'';
const publicCard=showPublic?`<article class="camera-card public-camera-card"><div class="camera-preview public-preview"><iframe id="public-preview" data-src="${esc(publicPreviewUrl())}" title="Preview live ATCS Denpasar · ${esc(publicCamera.name)}" allow="autoplay; fullscreen" sandbox="allow-scripts allow-same-origin allow-presentation" referrerpolicy="no-referrer" tabindex="-1"></iframe><span class="sample-watermark live-label">Live publik</span><span id="public-preview-error" class="public-preview-error" role="status" hidden></span><button class="public-preview-open" data-public-camera aria-label="Perbesar live ${esc(publicCamera.name)}"><span>Perbesar ↗</span></button></div><div class="cam-info"><div><h3>${esc(publicCamera.name)}</h3><p>${esc(publicCamera.zone)}</p></div><button data-public-camera aria-label="Detail live ${esc(publicCamera.name)}">↗</button></div></article>`:'';
$('#camera-grid').innerHTML=gatewayCard+publicCard+items.map(c=>`<article class="camera-card"><button class="camera-preview ${c.online?'':'cam-offline'}" data-camera="${esc(c.id)}" aria-label="${c.online?'Buka rekaman':'Lihat status'} ${esc(c.name)}">${c.mode==='archive'?'<span class="offline-icon" aria-hidden="true">◷</span><strong>Arsip rekaman</strong><p>Buka rekaman yang sudah disalin</p>':c.online?`<video class="grid-video" src="${esc(c.video)}" data-clip="${esc(c.id)}" muted loop playsinline preload="metadata" aria-label="Rekaman demo ${esc(c.name)}" tabindex="-1"></video><span class="sample-watermark">Rekaman demo</span><span class="preview-open">Buka rekaman ↗</span><span class="video-error" hidden>Video gagal dimuat. Buka pemutar untuk mencoba lagi.</span>`:'<span class="offline-icon" aria-hidden="true">⌁</span><strong>Kamera tidak terhubung</strong><p>Status contoh · offline</p>'}</button><div class="cam-info"><div><h3>${esc(c.name)}</h3><p>${esc(c.zone)}</p></div><button data-camera="${esc(c.id)}" aria-label="${c.online?'Detail rekaman':'Detail status'} ${esc(c.name)}">↗</button></div></article>`).join('')||'<div class="empty">Kamera tidak ditemukan. Ubah pencarian atau pilih Semua.</div>';
$$('[data-camera]').forEach(b=>b.onclick=()=>cameraDetail(b.dataset.camera));$$('[data-public-camera]').forEach(b=>b.onclick=openPublicCamera);$$('[data-gateway]').forEach(b=>b.onclick=()=>openGateway());
$$('.grid-video').forEach(v=>{v.muted=true;v.addEventListener('loadedmetadata',()=>{const c=cameras.find(c=>c.id===v.dataset.clip);if(Number.isFinite(v.duration)){c.seconds=v.duration;renderRecords()}});v.addEventListener('error',()=>{v.parentElement.querySelector('.video-error').hidden=false})});updateGridPlayback()
}
let publicPreviewSession;
function publicPreviewUrl(){
const url=new URL(publicCamera.embedUrl);
url.searchParams.set('autoplay','true');url.searchParams.set('muted','true');url.searchParams.set('controls','false');url.searchParams.set('playsinline','true');
return url.href;
}
function stopPublicPreview(){
if(publicPreviewSession){publicPreviewSession.abort();publicPreviewSession=undefined}
const frame=$('#public-preview');if(frame)frame.removeAttribute('src');
}
function updatePublicPreview(){
const frame=$('#public-preview');
const shouldPlay=frame&&location.hash==='#live'&&!document.hidden&&!$('#modal').open;
if(!shouldPlay){stopPublicPreview();return}
if(publicPreviewSession?.frame===frame)return;
stopPublicPreview();
const session=new AbortController();publicPreviewSession=session;session.frame=frame;
frame.src=frame.dataset.src;
let checking=false,needsRetry=false;
const error=$('#public-preview-error');
async function checkPreviewSource(){
if(checking||session.signal.aborted)return;checking=true;
try{
const response=await fetch(publicCamera.statusUrl,{credentials:'same-origin',headers:{Accept:'application/json'},cache:'no-store',signal:session.signal});
if(!response.ok)throw Error('Status sumber belum dapat diperiksa. Buka pemutar untuk mencoba lagi.');
const result=await response.json();
if(result.status==='available'){
error.hidden=true;if(needsRetry||!frame.hasAttribute('src'))frame.src=frame.dataset.src;needsRetry=false;
}else{
needsRetry=true;error.textContent=result.status==='stale'?'Sumber belum memperbarui data. Tayangan mungkin tertunda.':'Sumber sulit dijangkau. Jika tayangan berhenti, buka pemutar untuk mencoba lagi.';error.hidden=false;
}
}catch(problem){if(!session.signal.aborted){needsRetry=true;error.textContent=problem.message;error.hidden=false}}
finally{checking=false}
}
const timer=setInterval(checkPreviewSource,15000);session.signal.addEventListener('abort',()=>clearInterval(timer),{once:true});checkPreviewSource();
}

function openPublicCamera(){
if(!publicCamera)return;
modal('Live publik · '+publicCamera.name,`<div class="public-player-frame"><iframe id="public-player" src="${esc(publicCamera.embedUrl)}" title="Live ATCS Denpasar · ${esc(publicCamera.name)}" allow="autoplay; fullscreen" sandbox="allow-scripts allow-same-origin allow-presentation" referrerpolicy="no-referrer" allowfullscreen></iframe></div><div class="public-source-status"><p id="public-status" role="status">Memeriksa respons sumber…</p><button id="retry-public" class="secondary">Coba lagi</button></div><p class="help-text">Sumber: <a href="${esc(publicCamera.source)}" target="_blank" rel="noopener">ATCS Dishub Kota Denpasar ↗</a> · Zona waktu lokasi: WITA.</p><p class="help-text">Siaran publik ini belum direkam. Playback dan penanda posisi tersedia pada rekaman demo, bukan pada siaran ini.</p><p class="help-text">Jika video tidak tampil meskipun sumber merespons, coba lagi atau buka situs sumber.</p>`);
playerEvents=new AbortController();const signal=playerEvents.signal;let checking=false;
const frame=$('#public-player'),status=$('#public-status'),retry=$('#retry-public');
async function checkSource(reload=false){
if(checking||signal.aborted||document.hidden)return;
checking=true;retry.disabled=true;
try{
const response=await fetch(publicCamera.statusUrl,{credentials:'same-origin',headers:{Accept:'application/json'},cache:'no-store',signal});
if(!response.ok)throw Error(response.status===401||response.status===419?'Sesi berakhir. Muat ulang halaman dan masuk kembali.':'Status sumber belum dapat diperiksa.');
const result=await response.json();
const labels={available:'Sumber live merespons',stale:'Siaran belum memperbarui data. Coba lagi.',unavailable:'Sumber live tidak merespons. Coba lagi.'};
const checked=new Date(result.checkedAt).toLocaleTimeString('id-ID',{timeZone:'Asia/Jakarta',hour:'2-digit',minute:'2-digit',second:'2-digit'});
status.textContent=(labels[result.status]||'Status sumber belum diketahui.')+' · Dicek '+checked+' WIB';
status.dataset.state=result.status;
if(result.status==='available'){
if(reload||frame.getAttribute('src')==='about:blank')frame.src=publicCamera.embedUrl;
}else{frame.src='about:blank'}
}catch(error){if(!signal.aborted){status.textContent=error.message;status.dataset.state='unavailable';frame.src='about:blank'}}
finally{checking=false;if(!signal.aborted)retry.disabled=false}
}
retry.onclick=()=>checkSource(true);const timer=setInterval(()=>checkSource(),15000);signal.addEventListener('abort',()=>clearInterval(timer),{once:true});checkSource();
}

function cameraDetail(id){const c=cameras.find(c=>c.id===id);if(c.mode==='archive'&&!records.some(r=>cameras[r.cam].id===id)){toast('Belum ada arsip. Jalankan Backup sekarang untuk membaca folder rekaman.');return}if(c.online){openPlayer(c,records.find(r=>cameras[r.cam].id===id));return}modal(c.name,`<div class="warning"><span>ⓘ</span><div><strong>Contoh kamera offline</strong><p>Periksa sumber daya dan koneksi jaringan pada implementasi nyata.</p></div></div><div class="modal-actions"><button class="secondary" id="detail-connection">Atur koneksi</button></div>`);$('#detail-connection').onclick=()=>{$('#modal').close();page('settings')}}
function openPlayer(c,r,startAt=0){
if(r?.video)c={...c,video:r.video,mode:'archive',seconds:r.seconds||0};
const isArchive=c.mode==='archive';
const sourceDetails=isArchive?'<p class="help-text">Salinan MP4 dari folder sumber. File backup diperiksa sebelum disajikan. Waktu file belum tentu sama dengan waktu kamera. Retensi dapat menghapus salinan tanpa catatan kejadian.</p>':`<details class="source-details"><summary>Sumber dan informasi rekaman</summary><p class="source-credit">Footage uji dari <a href="${esc(c.source)}" target="_blank" rel="noopener">${esc(c.provider||'Mixkit')} · ${esc(c.sourceTitle)} ↗</a>. <a href="${esc(c.license||'https://mixkit.co/license/#videoFree')}" target="_blank" rel="noopener">Lisensi ↗</a>. ${esc(c.changes||'Ilustrasi lokasi, bukan CCTV perusahaan.')}</p><button class="secondary" id="export-record">Unduh informasi rekaman</button></details>`;

modal((isArchive?'Salinan rekaman · ':'Rekaman demo · ')+c.name,`<div class="player-frame"><video id="footage-player" src="${c.video}" controls playsinline preload="metadata" aria-label="Pemutar rekaman demo ${c.name}"></video><span class="player-label">${isArchive?'SALINAN REKAMAN · BUKAN LIVE':'REKAMAN DEMO · BUKAN LIVE'}</span></div><p id="player-state" class="muted" aria-live="polite">Memuat rekaman…</p><div class="playback-controls"><button class="secondary" id="skip-back">↶ 5 detik</button><button class="secondary" id="skip-forward">5 detik ↷</button><label>Kecepatan<select id="playback-speed"><option value="0.5">0,5×</option><option value="1" selected>1×</option><option value="1.5">1,5×</option><option value="2">2×</option></select></label><label class="loop-control"><input id="playback-loop" type="checkbox"> Ulangi</label></div><div class="clip-caption"><span>${esc(archiveLabel(r))}</span><span>Durasi klip: <b id="clip-duration">${clipDuration(c)}</b></span></div>${sourceDetails}<div class="modal-actions"><button class="primary" id="mark-incident">Catat kejadian pada <span id="mark-time">00:00</span></button></div><p class="player-note">Catatan menyimpan posisi rekaman agar mudah dibuka kembali.</p>`);
const v=$('#footage-player');playerEvents=new AbortController();
const on=(event,fn)=>v.addEventListener(event,fn,{signal:playerEvents.signal});let failed=false;const sync=()=>{$('#mark-time').textContent=timecode(v.currentTime)};
on('loadedmetadata',()=>{c.seconds=v.duration;if(r)r.seconds=v.duration;$('#clip-duration').textContent=clipDuration(c);v.currentTime=Math.min(Math.max(0,startAt),Math.max(0,v.duration-.1));sync();renderRecords();$('#player-state').textContent='Siap diputar · gunakan kontrol video untuk menggeser waktu.';v.play().catch(()=>{$('#player-state').textContent='Tekan tombol putar untuk memulai rekaman demo.'})});
on('timeupdate',sync);on('playing',()=>{failed=false;$('#player-state').textContent=isArchive?'Memutar salinan rekaman · bukan siaran langsung.':'Memutar rekaman demo · bukan siaran langsung.'});on('pause',()=>{if(!failed)$('#player-state').textContent='Dijeda pada '+timecode(v.currentTime)});on('ended',()=>{$('#player-state').textContent='Rekaman selesai. Geser ke awal atau aktifkan Ulangi.'});on('error',()=>{failed=true;$('#player-state').textContent='Video gagal dimuat. Tutup dan buka kembali pemutar untuk mencoba lagi.';$('#mark-incident').disabled=true});
$('#skip-back').onclick=()=>{v.currentTime=Math.max(0,v.currentTime-5);sync()};$('#skip-forward').onclick=()=>{v.currentTime=Math.min(Number.isFinite(v.duration)?v.duration:0,v.currentTime+5);sync()};$('#playback-speed').onchange=e=>{v.playbackRate=Number(e.target.value)};$('#playback-loop').onchange=e=>{v.loop=e.target.checked};
$('#mark-incident').onclick=()=>{const second=v.currentTime;v.pause();incidentForm(c.name,r?.id||'',{cameraId:c.id,second})};if($('#export-record'))$('#export-record').onclick=()=>download({mode:'rekaman-demo',record:r?.id,camera:c.name,archiveDate:r?.date,archiveTime:r?.start,timeBasis:r?.sourceTime?'source-timezone-unspecified':'example-WIB',attribution:c.sourceTitle,license:c.license||'https://mixkit.co/license/#videoFree',sourceFile:c.sourceFile,changes:c.changes,clipDurationSeconds:v.duration,positionSeconds:v.currentTime,source:c.source,live:false},(r?.id||c.id)+'-metadata-demo.json');
}
$$('#camera-tabs button').forEach(b=>b.onclick=()=>{filter=b.dataset.filter;$$('#camera-tabs button').forEach(x=>x.classList.toggle('selected',x===b));renderCameras()});$('#camera-search').oninput=e=>{query=e.target.value.toLowerCase();renderCameras()};$('#grid-view').onclick=()=>{$('#camera-grid').classList.remove('list');$('#grid-view').classList.add('selected');$('#list-view').classList.remove('selected')};$('#list-view').onclick=()=>{$('#camera-grid').classList.add('list');$('#list-view').classList.add('selected');$('#grid-view').classList.remove('selected')};
function renderRecords(){const name=$('#record-camera').value,date=$('#record-date').value;const rows=records.filter(r=>(name==='all'||cameras[r.cam].name===name)&&(!date||date===r.date));$('#record-rows').innerHTML=rows.map(r=>`<tr><td><strong>${esc(cameras[r.cam].name)}</strong></td><td><time datetime="${esc(r.date)}">${esc(r.date)}</time></td><td>${esc(r.start)}<br><small>${r.timeBasis==='file'?'Waktu file server':r.sourceTime?'Waktu sumber':'WIB · contoh'}</small></td><td>${r.seconds?timecode(r.seconds):cameras[r.cam].mode==='archive'?'Lihat pemutar':clipDuration(cameras[r.cam])}</td><td>${incidents.some(i=>i.record===r.id)?'<span class="status-badge">Ada catatan</span>':'—'}</td><td><button class="table-action" data-record="${esc(r.id)}">Putar</button></td></tr>`).join('')||'<tr><td colspan="6" class="empty">Rekaman tidak ditemukan. Ubah filter kamera atau tanggal.</td></tr>';$$('[data-record]').forEach(b=>b.onclick=()=>{const r=records.find(r=>r.id===b.dataset.record);openPlayer(cameras[r.cam],r)})}
$('#record-camera').onchange=renderRecords;$('#record-date').onchange=renderRecords;$('#reset-records').onclick=()=>{$('#record-camera').value='all';$('#record-date').value='';renderRecords()};
function download(obj,name){const url=URL.createObjectURL(new Blob([JSON.stringify(obj,null,2)],{type:'application/json'}));const a=document.createElement('a');a.href=url;a.download=name;a.click();setTimeout(()=>URL.revokeObjectURL(url),1000);toast('Metadata demo diunduh. Tidak memuat file video.')}
function renderIncidents(){$('#incident-count').textContent=incidents.length;$('#incident-list').innerHTML=incidents.map((i,n)=>`<article class="incident-row"><div><h3>${esc(i.title)}</h3><p>${esc(i.id)} · ${esc(i.camera)} · ${esc(i.date)}${i.clip?' · Posisi '+timecode(i.clip.second):''}</p><p>${esc(i.note)}</p></div><div class="incident-actions">${i.clip?(records.some(r=>r.id===i.record&&cameras[r.cam].id===i.clip.cameraId)?`<button class="table-action" data-replay="${n}">▶ Buka ${timecode(i.clip.second)}</button>`:'<span class="sample-label">Footage sebelumnya</span>'):''}${i.protected?'<span class="saved-note">Tersimpan</span>':`<button class="table-action" data-incident="${n}">Lindungi catatan</button>`}</div></article>`).join('')||'<p class="empty">Belum ada catatan insiden.</p>';$$('[data-incident]').forEach(b=>b.onclick=async()=>{const i=incidents[Number(b.dataset.incident)];if(i.protected){toast('Catatan sudah tersimpan. Perlindungan file video memerlukan server CCTV.');return}try{await api(window.SENTRA_BOOT.urls.protect.replace('__ID__',i.dbId),'PATCH',{});i.protected=true;renderIncidents();toast('Penandaan tersimpan di database.')}catch(error){toast(error.message)}});$$('[data-replay]').forEach(b=>b.onclick=()=>{const i=incidents[Number(b.dataset.replay)];const c=cameras.find(c=>c.id===i.clip?.cameraId&&c.online);const r=records.find(r=>r.id===i.record&&cameras[r.cam].id===c?.id);if(c&&r)openPlayer(c,r,Number(i.clip.second)||0);else toast('Footage sebelumnya sudah diganti. Catatan insiden tetap tersimpan.')})}
function incidentForm(camera='',record='',clip=null){modal('Catat kejadian',`<form id="incident-form">${clip?`<div class="form-note">Penanda rekaman demo: <strong>${timecode(clip.second)}</strong>. Posisi ini tersimpan bersama catatan.</div>`:''}<label>Judul kejadian<input name="title" required maxlength="120" placeholder="Contoh: aktivitas di area penyeberangan"></label><label>Kamera<select name="camera" ${clip?'disabled':''}>${cameras.map(c=>`<option ${c.name===camera?'selected':''}>${c.name}</option>`).join('')}</select></label><label>Tanggal kejadian<input name="date" type="date" value="${esc(records.find(r=>r.id===record)?.date||today)}" required></label><label>Catatan<textarea name="note" rows="3" maxlength="1000" placeholder="Tambahkan waktu dan detail kejadian">${record?'Referensi rekaman: '+record:''}</textarea></label><div class="form-note">Simpan informasi yang diperlukan untuk peninjauan. File video belum dicadangkan pada mode demo.</div><button class="primary">Simpan catatan</button></form>`);$('#incident-form').onsubmit=async e=>{e.preventDefault();const d=Object.fromEntries(new FormData(e.target));const b=e.target.querySelector('button');b.disabled=true;let created;try{created=await api(window.SENTRA_BOOT.urls.incidents,'POST',{...d,camera:clip?camera:d.camera,record,clip})}catch(error){toast(error.message);b.disabled=false;return}incidents=[created,...incidents];renderIncidents();renderRecords();$('#modal').close();page('incidents');toast('Catatan insiden dan posisi rekaman tersimpan di database.')};$('#incident-form input').focus()}
$('#new-incident').onclick=()=>incidentForm();$('#quick-incident').onclick=()=>incidentForm();$('#notification').onclick=()=>modal('Status sistem',`<p>${gatewayCamera.enabled?'Halaman player kamera dikonfigurasi. Pastikan tayangan bergerak.':'Kamera perusahaan belum terhubung. Video lokal dan siaran publik tetap untuk uji.'}</p><p>Status backup dan uji pemulihan dapat dilihat pada menu Backup. Pengaturan sumber tersedia dalam tiga langkah.</p>`);
initializeStorageSetup({boot:window.SENTRA_BOOT,api,esc,toast,preview:(url,name)=>openGateway(url,name),play:archive=>{
const camera=cameras.find(c=>c.id===archive.cameraId)||{id:archive.cameraId,name:archive.name,mode:'archive',seconds:0};
const record=records.find(r=>r.id===archive.record);openPlayer({...camera,mode:'archive',video:archive.playUrl},record);
}});
renderCameras();renderRecords();renderIncidents();page(location.hash.slice(1)||'live');
