// StockTrack/assets/app.js

async function suggestItems(q){
  const res = await fetch('/api/item_suggest.php?q=' + encodeURIComponent(q));
  return await res.json();
}

function attachItemSuggest(inputId, hiddenId, displayId = null){
  const input = document.getElementById(inputId);
  const hidden = hiddenId ? document.getElementById(hiddenId) : null;
  const display = displayId ? document.getElementById(displayId) : null;
  
  if(!input) return;
  
  input.parentElement.classList.add('suggest');
  const list = document.createElement('div');
  list.className='suggest-list';
  input.parentElement.appendChild(list);
  
  let tmr=null, last='';

  function hide(){ list.style.display='none'; list.innerHTML=''; }
  
  function show(items){
    if(!items || !items.length){ hide(); return; }
    list.innerHTML='';
    items.forEach(it=>{
      const row=document.createElement('div');
      let condLabel = '';
      if(it.condition === 'REPAIRED') condLabel = ' [🛠️ Repaired]';
      if(it.condition === 'FAULTY') condLabel = ' [⚠️ Faulty]';
      
      row.textContent = it.item_code + ' — ' + it.item_name + condLabel + ' (Qty: ' + it.qty + ')';
      
      row.onclick=()=>{
        input.value = it.item_name + condLabel;
        if(hidden) hidden.value = it.id;
        if(display) display.value = it.item_code;
        hide();
      };
      list.appendChild(row);
    });
    list.style.display='block';
  }

  input.addEventListener('input', ()=>{
    const q=input.value.trim();
    if(q.length<1){ hide(); return; }
    if(q===last) return;
    last=q;
    if(tmr) clearTimeout(tmr);
    tmr=setTimeout(async ()=>{ try{ show(await suggestItems(q)); }catch(e){ hide(); } }, 150);
  });
  input.addEventListener('blur', ()=>setTimeout(hide, 250));
}

// --- GLOBAL SCANNER & MODAL LOGIC ---

let gCurId = 0;
let gCurName = '';
let html5QrcodeScanner = null;

async function showItemDetails(id, name) {
    gCurId = id;
    gCurName = name;
    const m = document.getElementById('globalDetailModal');
    const t = document.getElementById('globalModalTitle');
    const b = document.getElementById('globalModalBody');
    
    if(m) m.style.display = 'block';
    if(t) t.innerText = name + ' (ID: ' + id + ')';
    if(b) {
        b.innerHTML = 'Loading...';
        try {
            const res = await fetch('/api/get_history.php?id=' + id);
            b.innerHTML = await res.text();
        } catch(e) { b.innerHTML = 'Error loading details.'; }
    }
}

function closeGlobalModal() {
    const m = document.getElementById('globalDetailModal');
    if(m) m.style.display='none';
}

async function doGlobalTransact(type, csrf, isReturn = false) {
    const qtyInput = document.getElementById('modalQty');
    const noteInput = document.getElementById('modalNote');
    const msg = document.getElementById('modalMsg');
    const chkRep = document.getElementById('mChkRep');
    const chkFlt = document.getElementById('mChkFlt');
    
    let cond = 'NEW';
    if(chkRep && chkRep.checked) cond = 'REPAIRED';
    if(chkFlt && chkFlt.checked) cond = 'FAULTY';
    
    const qty = qtyInput ? qtyInput.value : 0;
    const note = noteInput ? noteInput.value : '';

    if(!qty || qty <= 0) {
        if(msg) msg.innerHTML = '<span style="color:red">Invalid Qty</span>';
        return;
    }
    if(msg) msg.innerHTML = '<span style="color:#666">Processing...</span>';
    
    try {
        const res = await fetch('/api/transact.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({
                id: gCurId, type: type, qty: qty, note: note, condition: cond, is_return: isReturn, csrf: csrf
            })
        });
        const data = await res.json();
        if(data.error) {
             if(msg) msg.innerHTML = '<span style="color:red">'+data.error+'</span>';
        } else {
             if(msg) msg.innerHTML = '<span style="color:green">Success!</span>';
             await showItemDetails(gCurId, gCurName); // Refresh
        }
    } catch(e) { if(msg) msg.innerHTML = '<span style="color:red">Error</span>'; }
}

function mToggle(id) {
    const el = document.getElementById(id);
    if(el && el.checked) {
        if(id === 'mChkRep') { const f = document.getElementById('mChkFlt'); if(f) f.checked = false; }
        if(id === 'mChkFlt') { const r = document.getElementById('mChkRep'); if(r) r.checked = false; }
    }
}

function startGlobalScanner() {
    const m = document.getElementById('globalScannerModal');
    if(m) m.style.display = 'block';
    
    setTimeout(() => {
        if(html5QrcodeScanner) return; // Already running
        html5QrcodeScanner = new Html5Qrcode("globalReader");
        html5QrcodeScanner.start(
            { facingMode: "environment" },
            { fps: 10, qrbox: 250 },
            async (decodedText) => {
                await stopGlobalScanner();
                try {
                    const r = await fetch('/api/item_suggest.php?q=' + encodeURIComponent(decodedText));
                    const list = await r.json();
                    // Prefer exact code match
                    const item = list.find(x => x.item_code === decodedText) || list[0];
                    if(item) {
                        showItemDetails(item.id, item.item_name);
                    } else {
                        alert('Item not found: ' + decodedText);
                    }
                } catch(e) { alert('Lookup Error'); }
            },
            (err) => {}
        ).catch(e => { console.log(e); alert('Camera Error (HTTPS required?)'); stopGlobalScanner(); });
    }, 100);
}

async function stopGlobalScanner() {
    if(html5QrcodeScanner) {
        try { await html5QrcodeScanner.stop(); } catch(e){}
        html5QrcodeScanner = null;
    }
    const m = document.getElementById('globalScannerModal');
    if(m) m.style.display = 'none';
}

window.onclick = function(e) {
    const m1 = document.getElementById('globalDetailModal');
    const m2 = document.getElementById('globalScannerModal');
    if(e.target === m1) closeGlobalModal();
    if(e.target === m2) stopGlobalScanner();
}

// SW Registration
if ('serviceWorker' in navigator) {
  window.addEventListener('load', () => {
    navigator.serviceWorker.register('/sw.js').catch(err => console.log('SW failed:', err));
  });
}