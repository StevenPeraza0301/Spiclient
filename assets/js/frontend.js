document.addEventListener('DOMContentLoaded', function() {
  if (document.getElementById('spi-config')) {
      (function(){
        const $ = (id) => document.getElementById(id);

        // Refs
        const card = $('previewCard');
        const logoInput = $('logoInput'), logoPrev = $('logoPreview');
        const fondoInput = $('fondoInput'), fondoPrev = $('fondoPreview');
        const cp = $('colorPrimario'), ct = $('colorTexto');
        const provincia = $('provincia'), canton = $('canton');
        const nombre = $('nombreComercio');
        const totalSellos = $('totalSellos');
        const prevProvEl = $('prevProvincia'), prevCantEl = $('prevCanton'), prevBrand = $('prevBrand');
        const prevTotal = $('prevTotal');

        // Live colors
        function bindColor(input, prop){ if(!input) return; input.addEventListener('input', () => { if(card) card.style[prop] = input.value; updateA11y(); }); }
        bindColor(cp, 'backgroundColor'); bindColor(ct, 'color');

        // Live text
        function bindText(input, el, transform){ if(!input||!el) return; const upd = () => el.textContent = transform?transform(input.value):input.value; input.addEventListener('input', upd); upd(); }
        bindText(provincia, prevProvEl, v => (v||'').toUpperCase());
        bindText(canton,    prevCantEl);
        bindText(nombre,    prevBrand); // SOLO nombre (sin tipo)

        if (totalSellos && prevTotal) { const upd = () => { prevTotal.textContent = totalSellos.value; }; totalSellos.addEventListener('input', upd); upd(); }

        // Image previews
        function previewFile(input, imgEl, showIfEmpty=false){
          if(!input||!imgEl) return;
          input.addEventListener('change', () => {
            const f = input.files && input.files[0];
            if(!f){ if(showIfEmpty) imgEl.style.display='block'; return; }
            const reader = new FileReader();
            reader.onload = e => { imgEl.src = e.target.result; imgEl.style.display='block'; };
            reader.readAsDataURL(f);
          });
        }
        previewFile(logoInput,  logoPrev);
        previewFile(fondoInput, fondoPrev, true);

        // === A11Y helper ===
        const badge = $('a11yBadge'), ratioEl = $('a11yRatio'), suggestBtn = $('a11ySuggest');

        function hexToRgb(h){ h=h.replace('#',''); if(h.length===3){h=h.split('').map(c=>c+c).join('');} const num=parseInt(h,16); return {r:(num>>16)&255,g:(num>>8)&255,b:num&255}; }
        function luminance({r,g,b}){ const a=[r,g,b].map(v=>{v/=255;return v<=0.03928?v/12.92:Math.pow((v+0.055)/1.055,2.4)}); return 0.2126*a[0]+0.7152*a[1]+0.0722*a[2]; }
        function contrast(hex1,hex2){ const L1=luminance(hexToRgb(hex1)); const L2=luminance(hexToRgb(hex2)); const bright=Math.max(L1,L2), dark=Math.min(L1,L2); return (bright+0.05)/(dark+0.05); }

        function updateA11y(){
          try{
            const bg = toHex(getComputedStyle(card).backgroundColor) || cp.value;
            const fg = toHex(getComputedStyle(card).color) || ct.value;
            const ratio = contrast(bg, fg);
            ratioEl.textContent = 'Ratio: ' + ratio.toFixed(2);
            badge.classList.remove('ok','warn','fail');
            if (ratio >= 7) { badge.textContent='AAA'; badge.classList.add('ok'); }
            else if (ratio >= 4.5) { badge.textContent='AA'; badge.classList.add('ok'); }
            else if (ratio >= 3) { badge.textContent='Bajo'; badge.classList.add('warn'); }
            else { badge.textContent='Insuficiente'; badge.classList.add('fail'); }
          }catch(e){}
        }
        function toHex(rgb){
          if(!rgb) return null;
          const m = rgb.match(/rgba?\((\d+),\s*(\d+),\s*(\d+)/i); if(!m) return null;
          const r = (+m[1]).toString(16).padStart(2,'0');
          const g = (+m[2]).toString(16).padStart(2,'0');
          const b = (+m[3]).toString(16).padStart(2,'0');
          return '#'+r+g+b;
        }
        if(suggestBtn){
          suggestBtn.addEventListener('click', () => {
            try{
              const bg = cp.value || '#000000';
              // elige blanco o casi negro segn mejor contraste
              const white = '#ffffff', dark = '#111827';
              const pick = contrast(bg, white) >= contrast(bg, dark) ? white : dark;
              ct.value = pick;
              card.style.color = pick;
              updateA11y();
            }catch(e){}
          });
        }

        // Inicializa
        updateA11y();
        window.addEventListener('resize', updateA11y);
        // Asegura ancho de imgenes en mviles
        function enforceSizes(){ if(fondoPrev){ fondoPrev.style.width='100%'; fondoPrev.style.maxWidth='100%'; } }
        enforceSizes(); window.addEventListener('resize', enforceSizes);
      })();
  }
  if (document.getElementById('spi-card')) {
      (function(){
        document.body.classList.add('spi-painted');

        let device = null;
        let currentStep = 1;

        const formEl      = document.getElementById('spi-form');
        const backBtn     = document.getElementById('spi-back');
        const progressBar = document.getElementById('spi-progress');
        const subcopy     = document.getElementById('spi-subcopy');

        updateProgress();

        formEl.addEventListener('click', (e) => {
          const choice = e.target.closest('.choice');
          if (!choice) return;

          if (choice.dataset.device){
            device = choice.dataset.device;
            gotoStep(device === 'ios' ? 3 : 2);
            if (device === 'ios') setLinks('ios');
          }
          if (choice.dataset.app){
            gotoStep(3);
            setLinks(choice.dataset.app);
          }
        });

        backBtn.addEventListener('click', () => {
          if (currentStep === 3) gotoStep(device === 'ios' ? 1 : 2);
          else if (currentStep === 2) gotoStep(1);
        });

        function gotoStep(n){
          currentStep = n;
          formEl.querySelectorAll('.spi-step').forEach(s => s.classList.remove('active'));
          formEl.querySelector('[data-step="'+n+'"]').classList.add('active');
          backBtn.style.display = n > 1 ? 'inline-flex' : 'none';
          updateProgress();
        }

        function updateProgress(){ progressBar.style.width = ((currentStep - 1) / 2 * 100) + '%'; }

        function setLinks(key){
          let html = '';
          if (key === 'ios'){
            subcopy.textContent = 'Después de completar el formulario, la tarjeta se abrirá automáticamente en tu iPhone. Solo confirma para agregarla a Apple Wallet.';
            html = '';
          } else if (key === 'google'){
            subcopy.textContent = 'Instala Google Wallet para guardar tu tarjeta digital.';
            html = '<a class="download-cta google" target="_blank" rel="noopener" href="https://play.google.com/store/apps/details?id=com.google.android.apps.walletnfcrel">'+
                     '<span class="badge"><span class="mi">download</span></span>'+
                     '<div class="txt"><span class="title">Descargar Google Wallet</span><span class="sub">Desde Google Play</span></div>'+
                   '</a>';
          } else if (key === 'passwallet'){
            subcopy.textContent = 'Instala PassWallet para guardar tu tarjeta digital.';
            html = '<a class="download-cta passwallet" target="_blank" rel="noopener" href="https://play.google.com/store/apps/details?id=com.attidomobile.passwallet&hl=es">'+
                     '<span class="badge"><span class="mi">download</span></span>'+
                     '<div class="txt"><span class="title">Descargar PassWallet</span><span class="sub">Desde Google Play</span></div>'+
                   '</a>';
          }
          document.getElementById('spi-links').innerHTML = html;
        }

        window.addEventListener('unload', () => { document.body.classList.remove('spi-painted'); });
      })();
  }
  if (document.getElementById('spi-qr')) {
      (function(){
        const $ = (s, root=document) => root.querySelector(s);
        const $$ = (s, root=document) => Array.from(root.querySelectorAll(s));

        const root      = document.getElementById('spi-qr');
        const chips     = $$('.chip', root);
        const toast     = $('#qrToast', root);
        const copyBtn   = $('#copyBtn', root);
        const linkField = $('#linkField', root);
        const fullBtn   = $('#fullBtn', root);
        const overlay   = $('#qrOverlay', root);
        const closeOv   = $('#closeOverlay', root);

        // Tama09os
        const sizes = {
          sm: 'clamp(180px, 32vw, 260px)',
          md: 'clamp(220px, 42vw, 340px)',
          lg: 'clamp(260px, 54vw, 400px)'
        };
        function setSize(key){
          root.style.setProperty('--qr-size', sizes[key] || sizes.md);
          chips.forEach(c => c.setAttribute('aria-pressed', c.dataset.size === key ? 'true' : 'false'));
          localStorage.setItem('qr_size_pref', key);
        }
        // Inicial
        const saved = localStorage.getItem('qr_size_pref') || 'md';
        setSize(saved);

        chips.forEach(ch => {
          const key = ch.dataset.size;
          if (!key) return;
          ch.addEventListener('click', () => setSize(key));
        });

        // Copiar enlace
        function showToast(msg){
          if(!toast) return;
          toast.textContent = msg || 'Copiado';
          toast.classList.add('show');
          setTimeout(()=> toast.classList.remove('show'), 1500);
        }
        if (copyBtn && linkField) {
          copyBtn.addEventListener('click', async () => {
            try {
              await navigator.clipboard.writeText(linkField.textContent.trim());
              showToast('Enlace copiado');
            } catch(e) {
              const r = document.createRange(); r.selectNodeContents(linkField);
              const s = window.getSelection(); s.removeAllRanges(); s.addRange(r);
              document.execCommand('copy'); s.removeAllRanges();
              showToast('Enlace copiado');
            }
          });
        }

        // Fullscreen overlay
        if (fullBtn && overlay && closeOv) {
          fullBtn.addEventListener('click', () => { overlay.classList.add('open'); overlay.setAttribute('aria-hidden','false'); });
          closeOv.addEventListener('click', () => { overlay.classList.remove('open'); overlay.setAttribute('aria-hidden','true'); });
          overlay.addEventListener('click', (e) => { if (e.target === overlay) closeOv.click(); });
          document.addEventListener('keydown', (e) => { if (e.key === 'Escape') closeOv.click(); });
        }

        // Sincroniza tema con tu layout (usa body.dark)
        try {
          const savedTheme = localStorage.getItem('theme');
          if (savedTheme === 'dark') document.body.classList.add('dark');
          if (savedTheme === 'light') document.body.classList.remove('dark');
        } catch(e) {}
      })();
  }
  if (document.getElementById('spi-scan')) {
    (function(){
      const ready = () => typeof window.Html5Qrcode !== 'undefined';
      function whenReady(cb, tries=40){ if(ready()) return cb(); if(tries<=0) return console.error('html5-qrcode no cargÃ³'); setTimeout(()=>whenReady(cb, tries-1), 100); }
      whenReady(init);

      function init(){
        const cfg = window.spiQRConfig || {};
        const readerEl = document.getElementById('reader');
        const cameraSel= document.getElementById('cameraSelect');
        const toggle   = document.getElementById('toggleScan');
        const scanAgain= document.getElementById('scanAgain');
        const fileBtn  = document.getElementById('fileBtn');
        const fileInp  = document.getElementById('fileInput');
        const manual   = document.getElementById('manualCode');
        const manualGo = document.getElementById('manualSubmit');

        const scanStatus = document.getElementById('scanStatus');
        const estadoVal  = document.getElementById('estadoVal');
        const codeVal    = document.getElementById('codeVal');
        const origenVal  = document.getElementById('origenVal');
        const msgVal     = document.getElementById('msgVal');
        const sellosBox  = document.getElementById('sellosBox');
        const sellosAct  = document.getElementById('sellosAct');
        const sellosRest = document.getElementById('sellosRest');
        const toast      = document.getElementById('scanToast');

        const redeemModal  = document.getElementById('redeemModal');
        const redeemConfirm= document.getElementById('redeemConfirm');
        const redeemCancel = document.getElementById('redeemCancel');

        const html5 = new Html5Qrcode('reader');
        let scanning=false, locking=false;
        let currentTrack=null;

        // Zoom UI
        const zoomWrap  = document.getElementById('zoomWrap');
        const zoomSlider= document.getElementById('zoomSlider');

        function toastMsg(t){ toast.textContent=t||'Listo'; toast.classList.add('show'); setTimeout(()=>toast.classList.remove('show'),1400); }
        function status(type,text){
          scanStatus.className='status '+(type||'warn');
          scanStatus.style.display='flex';
          scanStatus.innerHTML='<span class="material-symbols-rounded">'+(type==='success'?'task_alt':type==='error'?'error':'info')+'</span>'+ (text||'');
        }
        function row(el,val,badge){ el.innerHTML = badge?('<span class="badge">'+(val||'â')+'</span>'):(val||'â'); }

        function parseOrigen(code){
          try{ const u=new URL(code); const c=u.searchParams.get('comercio'); return {tipo:'URL', comercio:c}; }catch(_){ return {tipo:'Texto', comercio:null}; }
        }

        // === CÃ¡mara: pasar SOLO UNA clave en cameraIdOrConfig ===
        function getCameraConfig(deviceId){
          return deviceId
            ? { deviceId: { exact: deviceId } }
            : { facingMode: { exact: "environment" } };
        }

        // Config propia de html5-qrcode (no meter aquÃ­ keys de cÃ¡mara)
        function qrBoxSize(){
          const base = Math.min(520, Math.floor(window.innerWidth * 0.8));
          return { width: base, height: base };
        }
        const scanConfig = {
          fps: 12,
          qrbox: qrBoxSize(),
          aspectRatio: 1.0,
          experimentalFeatures: { useBarCodeDetectorIfSupported: true }
        };

        async function applyZoom(track, value){
          try{
            const caps = track.getCapabilities ? track.getCapabilities() : {};
            if (!caps.zoom) return false;
            const clamped = Math.min(caps.zoom.max || 1, Math.max(caps.zoom.min || 1, value));
            await track.applyConstraints({ advanced: [{ zoom: clamped }] });
            return { min: caps.zoom.min || 1, max: caps.zoom.max || 1, value: clamped, step: caps.zoom.step || 0.01 };
          }catch{ return false; }
        }

        function exposeZoom(track){
          try{
            const caps = track.getCapabilities ? track.getCapabilities() : {};
            if (!caps.zoom) return zoomWrap.style.display='none';
            zoomWrap.style.display='flex';
            zoomSlider.min = caps.zoom.min || 1;
            zoomSlider.max = caps.zoom.max || 1;
            zoomSlider.step= caps.zoom.step || 0.01;
            zoomSlider.value = Math.min(zoomSlider.max, Math.max(zoomSlider.min, zoomSlider.value || zoomSlider.min));
            zoomSlider.oninput = () => applyZoom(track, parseFloat(zoomSlider.value));
          }catch{ zoomWrap.style.display='none'; }
        }

        function currentVideoTrack(){
          try{
            const el = readerEl.querySelector('video');
            const stream = el && el.srcObject;
            const track = stream && stream.getVideoTracks ? stream.getVideoTracks()[0] : null;
            return track || null;
          }catch{ return null; }
        }

        async function tuneTrack(track){
          if (!track) return;
          try{
            await track.applyConstraints({ advanced: [{ focusMode: 'continuous' }, { exposureMode: 'continuous' }] });
          }catch(_){}
          exposeZoom(track);
          // Zoom leve por defecto si hay soporte
          const applied = await applyZoom(track, 1.5);
          if (applied) zoomSlider.value = applied.value;
        }

        // ==== Flujo principal ====
        async function process(code){
          if(!code || locking) return;
          locking=true;

          // al detectar un cÃ³digo, paramos y mostramos botÃ³n "Escanear otro"
          stop(false);
          scanAgain.style.display = 'inline-flex';

          row(codeVal, code);
          const origen = parseOrigen(code);
          row(origenVal, origen.tipo + (origen.comercio?(' Â· comercio='+origen.comercio):''));

          try{
            const res = await fetch(cfg.ajax, {
              method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'},
              body:new URLSearchParams({ action:'spi_sumar_sello', codigo_qr: code })
            });
            if(!res.ok) throw new Error('HTTP '+res.status);
            const data = await res.json();

            const msg = (data && data.data && data.data.mensaje) ? data.data.mensaje :
                        (data && typeof data.data === 'string' ? data.data : '');

            // Sellos
            sellosBox.style.display = 'none';
            if (data && data.data && (data.data.sellos_actuales!==undefined || data.data.sellos_restantes!==undefined)){
              sellosAct.textContent  = 'Sellos: ' + (data.data.sellos_actuales ?? 'â');
              sellosRest.textContent = 'Restantes: ' + (data.data.sellos_restantes ?? 'â');
              sellosBox.style.display = 'flex';
            }

            // Comercio distinto
            let mismatch = false;
            if (data && data.data && data.data.comercio_esperado && data.data.comercio_detectado && String(data.data.comercio_esperado) !== String(data.data.comercio_detectado)) mismatch = true;
            if (origen.comercio && String(origen.comercio) !== String(cfg.userId)) mismatch = true;

            if (data.success && !mismatch){
              status('success','Sello agregado correctamente.');
              row(estadoVal,'OK',true);
              row(msgVal, msg || 'Operaci&oacute;n exitosa');
            } else {
              const normalized = (msg || '').toLowerCase();
              if (normalized.includes('alcanz') && normalized.includes('m\u00e1ximo')) {
                row(estadoVal,'M&aacute;ximo de sellos',true);
                row(msgVal, 'El cliente alcanz&oacute; el l&iacute;mite. Puedes redimir ahora.');
                openRedeemModal(code);
              } else if (normalized.includes('no encontrado')) {
                status('error','Cliente no encontrado o de otro comercio.');
                row(estadoVal,'No v&aacute;lido',true);
                row(msgVal, 'Verifica el c&oacute;digo o el comercio.');
              } else if (mismatch) {
                status('error','El c&oacute;digo pertenece a otro comercio.');
                row(estadoVal,'Comercio distinto',true);
                row(msgVal, msg || 'No v&aacute;lido para este comercio.');
              } else {
                status('error','No se pudo procesar el c&oacute;digo.');
                row(estadoVal,'Error',true);
                row(msgVal, msg || 'Int&eacute;ntalo nuevamente.');
              }
            }
          }catch(e){
            status('error','Error de red o permisos de c&aacute;mara.');
            row(estadoVal,'Error',true); row(msgVal,e.message||String(e));
          }finally{
            toastMsg('Procesado');
            locking=false;
          }
        }

        function httpsOk(){ return location.protocol==='https:' || location.hostname==='localhost'; }

        function start(deviceId){
          if(!httpsOk()){ status('error','Necesitas HTTPS para usar la c&aacute;mara. Usa &ldquo;Leer foto&rdquo;.'); return; }

          html5.start(
            getCameraConfig(deviceId), // â SOLO 1 clave: deviceId o facingMode
            scanConfig,                // âï¸ opciones del lector (fps, qrbox, etc.)
            (msg)=>{ process(msg); },  // Ya no paramos aquÃ­; paramos dentro de process()
            (_)=>{}
          ).then(async ()=>{
            scanning=true;
            toggle.className='btn btn-danger'; 
            toggle.innerHTML='<span class="material-symbols-rounded">stop</span> Detener';
            scanAgain.style.display='none';

            // Afinar track DESPUÃS de iniciar
            currentTrack = currentVideoTrack();
            await tuneTrack(currentTrack);
          }).catch(err=>{
            status('error','No se pudo iniciar la c&aacute;mara. Revisa permisos.');
            row(msgVal, err && err.message ? err.message : String(err));
          });
        }

        function stop(updateUI=true){
          html5.stop().catch(()=>{}).finally(()=>{
            scanning=false; currentTrack=null;
            zoomWrap.style.display='none';
            if(updateUI){
              toggle.className='btn btn-primary'; 
              toggle.innerHTML='<span class="material-symbols-rounded">play_arrow</span> Iniciar';
            }
          });
        }

        function restart(){
          stop();
          setTimeout(()=> start(cameraSel.value || null), 220);
        }

        // Poblar cÃ¡maras al iniciar (tras gesto)
        toggle.addEventListener('click', async ()=>{
          if(scanning){ stop(); return; }
          try{
            const cams = await Html5Qrcode.getCameras();
            cameraSel.innerHTML = '';
            cams.forEach((d,i)=>{ const o=document.createElement('option'); o.value=d.id; o.textContent=d.label || ('CÃ¡mara '+(i+1)); cameraSel.appendChild(o); });
          }catch(_){}
          start(cameraSel.value || null);
        });

        // BotÃ³n Escanear otro (reinicia lector y limpia estado)
        scanAgain.addEventListener('click', ()=>{
          // limpiar UI
          document.getElementById('scanStatus').style.display='none';
          row(estadoVal, '<span class="badge">En espera</span>');
          row(codeVal, 'â');
          row(origenVal, 'â');
          row(msgVal, 'â');
          sellosBox.style.display='none';
          // volver a iniciar
          start(cameraSel.value || null);
        });

        cameraSel.addEventListener('change', ()=> { if(scanning) restart(); });

        // Leer desde foto
        fileBtn.addEventListener('click', ()=> fileInp.click());
        fileInp.addEventListener('change', async ()=>{
          const f = fileInp.files && fileInp.files[0];
          if(!f) return;
          try{ const r = await html5.scanFile(f, true); process(r); }
          catch(e){ status('error','No se detect&oacute; un QR en la imagen.'); msgVal.innerText = e.message||String(e); }
          finally{ fileInp.value=''; }
        });

        // Manual
        manualGo.addEventListener('click', ()=>{
          const code=(manual.value||'').trim();
          if(!code){ status('warn','Debes ingresar un c&oacute;digo.'); return; }
          process(code);
        });

        // Tema
        try{ const t=localStorage.getItem('theme'); if(t==='dark') document.body.classList.add('dark'); if(t==='light') document.body.classList.remove('dark'); }catch(e){}

        // Estado inicial
        status('warn','Listo para escanear. Presiona <strong>Iniciar</strong>.');

        // ==== Modal Redimir ====
        function openRedeemModal(code){
          redeemModal.classList.add('open'); 
          redeemModal.setAttribute('aria-hidden','false');

          const onKey = (e)=>{ if(e.key==='Escape'){ close(); } };
          function close(){
            redeemModal.classList.remove('open'); 
            redeemModal.setAttribute('aria-hidden','true');
            document.removeEventListener('keydown', onKey);
          }
          redeemCancel.onclick = close;
          redeemModal.onclick = (e)=>{ if(e.target===redeemModal) close(); };
          document.addEventListener('keydown', onKey);

          let busy=false;
          redeemConfirm.onclick = async ()=>{
            if(busy) return; busy=true; redeemConfirm.disabled=true;
            try{
              // El endpoint debe: reiniciar sellos, regenerar pass y ENVIAR CORREO con email-recompensa.php
              const res = await fetch(cfg.ajax, {
                method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'},
                body:new URLSearchParams({ action:'spi_reiniciar_sellos', codigo_qr: code })
              });

  const helpGuide = document.querySelector('.spi-help-guide');
  if (helpGuide) {
    const menuLinks = helpGuide.querySelectorAll('.spi-help-menu a');
    menuLinks.forEach(link => {
      link.addEventListener('click', e => {
        e.preventDefault();
        const target = helpGuide.querySelector(link.getAttribute('href'));
        if (target) target.scrollIntoView({ behavior: 'smooth' });
      });
    });
    const faqs = helpGuide.querySelectorAll('.spi-faq details');
    faqs.forEach(d => {
      d.addEventListener('toggle', () => {
        if (d.open) faqs.forEach(o => { if (o !== d) o.open = false; });
      });
    });
  }
              if(!res.ok) throw new Error('HTTP '+res.status);
              const data = await res.json();

              if (data.success){
                status('success','Recompensa redimida. La tarjeta se actualizó automáticamente en el dispositivo del cliente.');
                row(estadoVal,'Redimido',true);
                row(msgVal,'Tarjeta actualizada automáticamente + correo enviado como respaldo.');
                close();
                // mostrar botÃ³n para continuar con otros escaneos
                scanAgain.style.display = 'inline-flex';
              } else {
                status('error','No se pudo redimir.');
                row(estadoVal,'Error',true);
                row(msgVal,(data.data && data.data.mensaje) ? data.data.mensaje : (data.data || 'Int&eacute;ntalo m&aacute;s tarde.'));
              }
            }catch(e){
              status('error','Error de red al redimir.');
              row(estadoVal,'Error',true); row(msgVal,e.message||String(e));
            }finally{
              busy=false; redeemConfirm.disabled=false;
            }
          }
        }
      }
    })();
  }
});
