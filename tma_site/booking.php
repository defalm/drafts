<?php
require __DIR__ . '/lib.php';
require __DIR__ . '/layout.php';
$S = S(); $c = $S['contacts'];
$pub = [];
foreach (services() as $id => $s) {
  if ($s['kind'] === 'quest') { [$mn, $mx] = ppl_bounds($id);
    $pub[$id] = ['name' => $s['name'], 'kind' => 'quest', 'sub' => (int)$S['quest_schedule']['duration_min'] . ' мин', 'min' => $mn, 'max' => $mx,
      'price' => num($s['price']), 'base' => num($s['players_base']), 'add' => num($s['extra_player_price']), 'ph' => $s['price']]; }
  else $pub[$id] = ['name' => $s['name'], 'kind' => 'cinema', 'sub' => 'сборный / приватный', 'seat' => num($s['seat_price']), 'priv' => num($s['private_price']),
      'phSeat' => $s['seat_price'], 'phPriv' => $s['private_price']];
}
$sel = isset($pub[$_GET['s'] ?? '']) ? (string)$_GET['s'] : '';
page_head('Бронирование — «ТЬМА», хоррор-квесты в Ялте', 'Онлайн-бронирование хоррор-квестов и кинопросмотра «ТЬМА» в Ялте.');
?>
<main class="bk"><div class="wrap">
  <div class="eyebrow">Онлайн-бронирование</div>
  <h1>Забронировать</h1>
  <p class="lead">Сеансы <?= h($c['hours']) ?>. Пн–Ср закрыто.</p>

  <div class="bkgrid">
  <div>
    <div class="stp"><h4><i>01</i> Услуга</h4><div class="svc" id="svc"></div></div>
    <div class="stp" id="st2" hidden><h4><i>02</i> Дата</h4><div class="days" id="days"></div></div>
    <div class="stp" id="st3" hidden><h4><i>03</i> Сеанс</h4><div id="modeline"></div><div class="slots" id="slots"></div><p class="hint" id="slotHint"></p></div>
  </div>
  <div>
    <div class="stp" id="st4" hidden>
      <div id="fwrap">
        <h4><i>04</i> Ваши данные</h4>
        <div id="qtyBox"><p class="hint" id="qtyLabel" style="margin:0 0 8px"></p>
          <div class="cnt" id="cnt"><button type="button" id="minus" aria-label="меньше">−</button><b id="qty">1</b><button type="button" id="plus" aria-label="больше">+</button></div></div>
        <p class="hint" id="privNote" hidden>Приватный сеанс: бронируется весь зал для вашей компании.</p>
        <div class="sum">
          <div><span>Услуга</span><b id="sS">—</b></div>
          <div><span>Дата и время</span><b id="sD">—</b></div>
          <div><span id="sQl">Игроков</span><b id="sQ">—</b></div>
          <div class="tot"><span>Стоимость</span><b id="sP">—</b></div>
        </div>
        <input class="inp" id="nm" placeholder="Ваше имя" autocomplete="name" maxlength="60">
        <input class="inp" id="ph" placeholder="Телефон" type="tel" autocomplete="tel" inputmode="tel" maxlength="30">
        <input class="inp" id="cm" placeholder="Комментарий (необязательно)" maxlength="300">
        <input type="text" id="hp" tabindex="-1" autocomplete="off" aria-hidden="true" style="position:absolute;left:-9999px;width:1px;height:1px;opacity:0">
        <label class="chk"><input type="checkbox" id="agree"><span>Даю <a href="consent.html" target="_blank">согласие на обработку персональных данных</a> и принимаю <a href="privacy.html" target="_blank">политику обработки персональных данных</a></span></label>
        <button class="btn" style="width:100%" id="go" type="button">Отправить заявку</button>
        <p class="err" id="err" role="alert"></p>
        <p class="hint">После отправки администратор свяжется с вами для подтверждения брони.</p>
      </div>
      <div class="done" id="done">
        <div class="eyebrow">Готово</div>
        <div class="t" id="doneT">Заявка отправлена</div>
        <p id="doneTxt"></p>
        <p id="doneDemo" hidden style="font-size:14px">В рабочей версии заявка сразу приходит администратору в Telegram с кнопками «Подтвердить / Отменить», а подтверждённый сеанс закрывается на сайте.</p>
        <p style="font-size:14px">Вопросы: <a href="tel:<?= h($c['phone_href']) ?>"><?= h($c['phone']) ?></a></p>
        <button class="btn ghost" id="again" type="button">Новая заявка</button>
      </div>
    </div>
  </div>
  </div>
</div></main>
<script>
(function(){
const SVC=<?= json_encode($pub, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) ?>, PHONE=<?= json_encode($c['phone'], JSON_UNESCAPED_UNICODE) ?>;
const $=s=>document.querySelector(s);
let svc=<?= json_encode($sel) ?>||null, data=null, di=-1, slot=null, qty=1;
const fmt=n=>n.toLocaleString('ru-RU')+' ₽';
function day(){return data&&di>=0?data.days[di]:null}
function mode(){const d=day();return d?d.mode:(SVC[svc]&&SVC[svc].kind==='quest'?'quest':null)}
function maxQty(){const m=mode();if(m==='quest')return SVC[svc].max;if(m==='group'){const s=day().slots.find(x=>x.t===slot);return s?Math.max(1,s.left):data.seats_total}return 0}
function minQty(){return mode()==='quest'?SVC[svc].min:1}
function price(){const s=SVC[svc],m=mode();
  if(m==='quest'){if(s.price===null)return {t:s.ph+' (уточнит администратор)'};let p=s.price;if(s.base!==null&&s.add!==null)p+=Math.max(0,qty-s.base)*s.add;return {t:fmt(p)}}
  if(m==='group')return s.seat===null?{t:s.phSeat+' × '+qty}:{t:fmt(s.seat*qty)};
  return s.priv===null?{t:s.phPriv}:{t:fmt(s.priv)}}

function rSvc(){const el=$('#svc');el.innerHTML='';Object.entries(SVC).forEach(([id,s])=>{const b=document.createElement('button');b.type='button';if(id===svc)b.className='on';
  b.innerHTML=(s.kind==='quest'?'Квест «'+s.name+'»':s.name)+'<small>'+s.sub+'</small>';b.onclick=()=>pick(id);el.appendChild(b)})}
function pick(id){svc=id;slot=null;di=-1;rSvc();history.replaceState(null,'','?s='+id);$('#st2').hidden=false;$('#st3').hidden=true;$('#st4').hidden=true;load()}
function load(){$('#days').innerHTML='<p class="hint">Загружаем расписание…</p>';const cur=svc;
  return fetch('api/slots.php?s='+encodeURIComponent(cur),{cache:'no-store'}).then(r=>r.json()).then(j=>{if(cur!==svc)return;data=j;
    if(di<0){di=data.days.findIndex(d=>d.work&&d.slots.some(s=>s.free))}rDays();if(di>=0){$('#st3').hidden=false;rSlots()}})
  .catch(()=>{$('#days').innerHTML='<p class="err">Не удалось загрузить расписание. Позвоните нам: '+PHONE+'</p>'})}
function rDays(){const el=$('#days');el.innerHTML='';data.days.forEach((d,i)=>{const b=document.createElement('button');b.type='button';
  const free=d.work&&d.slots.some(s=>s.free);b.disabled=!free;b.title=d.work?(free?'':'нет свободных сеансов'):'выходной';if(i===di)b.className='on';
  b.innerHTML='<small>'+d.dow+'</small>'+d.day+'<small>'+(d.work?d.mon:'вых')+'</small>';b.onclick=()=>{di=i;slot=null;rDays();$('#st3').hidden=false;$('#st4').hidden=true;rSlots()};el.appendChild(b)});
  const on=el.querySelector('.on');if(on)on.scrollIntoView({block:'nearest',inline:'nearest'})}
function rSlots(){const d=day(),el=$('#slots');el.innerHTML='';const m=d.mode;
  $('#modeline').innerHTML=m==='group'?'<span class="mode">Сборный сеанс · продажа мест</span>':m==='private'?'<span class="mode">Приватный сеанс · весь зал</span>':'';
  d.slots.forEach(s=>{const b=document.createElement('button');b.type='button';b.disabled=!s.free;if(s.t===slot)b.className='on';
    b.innerHTML=s.t+'<small>'+(s.past?'закрыто':!s.free?'занято':m==='group'?'мест: '+s.left:'свободно')+'</small>';
    b.onclick=()=>{slot=s.t;if(m!=='private')qty=Math.min(Math.max(qty,minQty()),maxQty());rSlots();form()};el.appendChild(b)});
  $('#slotHint').textContent=m==='quest'?'Сеанс 60 минут, начало каждый час.':'Сеансы каждые 2 часа.'}
function form(){$('#st4').hidden=false;$('#fwrap').style.display='';$('#done').style.display='none';const m=mode(),d=day(),s=SVC[svc];
  $('#qtyBox').hidden=m==='private';$('#privNote').hidden=m!=='private';
  $('#qtyLabel').textContent=m==='quest'?'Сколько игроков (от '+s.min+' до '+s.max+')':'Сколько мест (свободно: '+maxQty()+')';
  $('#qty').textContent=qty;$('#sS').textContent=s.kind==='quest'?'Квест «'+s.name+'»':s.name+(m==='group'?' · сборный':' · приватный');
  $('#sD').textContent=d.label+', '+slot;$('#sQl').textContent=m==='quest'?'Игроков':'Места';$('#sQ').textContent=m==='private'?'весь зал':qty;$('#sP').textContent=price().t;
  if(innerWidth<900)$('#st4').scrollIntoView({behavior:'smooth',block:'start'})}
$('#minus').onclick=()=>{qty=Math.max(minQty(),qty-1);form()};
$('#plus').onclick=()=>{qty=Math.min(maxQty(),qty+1);form()};
$('#again').onclick=()=>{slot=null;$('#st4').hidden=true;load()};
$('#go').onclick=async()=>{const er=$('#err');er.textContent='';
  const nm=$('#nm').value.trim(),ph=$('#ph').value.trim();
  if(!slot){er.textContent='Выберите сеанс';return}
  if(nm.length<2){er.textContent='Укажите имя';$('#nm').focus();return}
  if(ph.replace(/\D/g,'').length<10){er.textContent='Укажите телефон';$('#ph').focus();return}
  if(!$('#agree').checked){er.textContent='Отметьте согласие на обработку персональных данных';return}
  const go=$('#go');go.disabled=true;go.textContent='Отправляем…';
  try{const r=await fetch('api/book.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({service:svc,day:day().date,time:slot,qty:mode()==='private'?0:qty,name:nm,contact:ph,comment:$('#cm').value.trim(),consent:true,website:$('#hp').value})});
    const j=await r.json().catch(()=>({}));
    if(!r.ok||!j.ok){er.textContent=j.error||('Не получилось отправить. Позвоните нам: '+PHONE);if(j.taken){slot=null;await load()}return}
    $('#doneT').textContent=j.demo?'Заявка отправлена (демо)':'Заявка отправлена';$('#doneDemo').hidden=!j.demo;
    $('#doneTxt').textContent=$('#sS').textContent+' · '+j.label+(mode()==='private'?' · весь зал':' · '+qty+(mode()==='quest'?' игр.':' мест'));
    $('#fwrap').style.display='none';$('#done').style.display='block';$('#done').scrollIntoView({behavior:'smooth',block:'center'});
  }catch(e){er.textContent='Нет связи. Попробуйте ещё раз или позвоните: '+PHONE}
  finally{go.disabled=false;go.textContent='Отправить заявку'}};
rSvc();if(svc)pick(svc);
})();
</script>
<?php page_foot(false);
