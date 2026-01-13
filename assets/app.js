async function suggestItems(q){
  const res = await fetch('/api/item_suggest.php?q=' + encodeURIComponent(q));
  return await res.json();
}

// Updated to accept an optional 'displayId' to show code separately
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
      
      // Show Condition if not NEW
      let condLabel = '';
      if(it.condition === 'REPAIRED') condLabel = ' [🛠️ Repaired]';
      if(it.condition === 'FAULTY') condLabel = ' [⚠️ Faulty]';
      
      row.textContent = it.item_code + ' — ' + it.item_name + condLabel + ' (Qty: ' + it.qty + ')';
      
      row.onclick=()=>{
        input.value = it.item_name + condLabel;
        if(hidden) hidden.value = it.id; // Store unique ID
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

// SW Registration
if ('serviceWorker' in navigator) {
  window.addEventListener('load', () => {
    navigator.serviceWorker.register('/sw.js').catch(err => console.log('SW failed:', err));
  });
}