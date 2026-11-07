(function(){
'use strict';
const $=s=>document.querySelector(s),$$=s=>[...document.querySelectorAll(s)];
const RM=matchMedia('(prefers-reduced-motion: reduce)').matches;
const onView=(el,cb,th)=>{if(!el)return;const io=new IntersectionObserver(es=>es.forEach(e=>cb(e.isIntersecting)),{threshold:th||0});io.observe(el)};

// ---- плёночное зерно ----
const g=$('#grain');
if(g&&!RM){const c=g.getContext('2d');let img,last=0;
  const size=()=>{g.width=Math.ceil(innerWidth/3);g.height=Math.ceil(innerHeight/3);img=c.createImageData(g.width,g.height)};size();addEventListener('resize',size);
  (function f(t){if(t-last>90){last=t;const d=img.data;for(let i=0;i<d.length;i+=4){const v=Math.random()*255|0;d[i]=d[i+1]=d[i+2]=v;d[i+3]=255}c.putImageData(img,0,0)}requestAnimationFrame(f)})(0)}

// ---- фонарик на первом экране ----
const hero=$('#hero'),dark=$('#dark'),hint=$('#hint'),eyes=$$('.eyes b');
if(hero&&dark){
  let tx=.5,ty=.42,cx=.5,cy=.42,idle=999,touched=false,run=false,W=0,H=0,R=190,E=[];
  const measure=()=>{const r=hero.getBoundingClientRect();W=r.width;H=r.height;R=Math.max(150,Math.min(300,Math.min(W,H)*.42));dark.style.setProperty('--rad',R+'px');
    E=eyes.map(e=>{const b=e.getBoundingClientRect();return{el:e,x:b.left-r.left+b.width/2,y:b.top-r.top+b.height/2}})};
  const aim=(x,y)=>{const r=hero.getBoundingClientRect();tx=(x-r.left)/r.width;ty=(y-r.top)/r.height;idle=0;if(!touched){touched=true;hint&&hint.classList.add('gone')}};
  hero.addEventListener('pointermove',e=>aim(e.clientX,e.clientY));
  hero.addEventListener('pointerdown',e=>aim(e.clientX,e.clientY));
  hero.addEventListener('touchmove',e=>{const t=e.touches[0];if(t)aim(t.clientX,t.clientY)},{passive:true});
  addEventListener('resize',measure);measure();
  function frame(t){if(!run)return;idle++;
    if(idle>110&&!RM){tx=.5+.33*Math.sin(t/1900);ty=.46+.22*Math.sin(t/1270)}
    cx+=(tx-cx)*.09;cy+=(ty-cy)*.09;
    dark.style.setProperty('--x',(cx*100).toFixed(2)+'%');dark.style.setProperty('--y',(cy*100).toFixed(2)+'%');
    const px=cx*W,py=cy*H;E.forEach(o=>o.el.classList.toggle('shut',Math.hypot(o.x-px,o.y-py)<R*.6));
    requestAnimationFrame(frame)}
  onView(hero,v=>{if(v&&!run){run=true;measure();requestAnimationFrame(frame)}else if(!v)run=false});
}

// ---- двери ----
const doors=$$('.door:not(.locked)');
doors.forEach(d=>{const l=d.querySelector('.leaf');l.addEventListener('click',()=>{
  d.classList.add('open');l.setAttribute('aria-expanded','true');
  const a=d.querySelector('.room .btn');setTimeout(()=>a&&a.focus({preventScroll:true}),650)})});
$$('.door.locked').forEach(d=>{const sh=()=>{d.classList.remove('shake');void d.offsetWidth;d.classList.add('shake')};d.addEventListener('click',sh);d.addEventListener('keydown',e=>{if(e.key==='Enter'||e.key===' '){e.preventDefault();sh()}})});
// кто-то стучит изнутри
if(!RM&&doors.length){let vis=false;onView($('#quests'),v=>vis=v,.2);
  setInterval(()=>{if(!vis||document.hidden)return;const c=doors.filter(d=>!d.classList.contains('open')&&!d.matches(':hover'));if(!c.length)return;
    const d=c[Math.random()*c.length|0];d.classList.add('knock');setTimeout(()=>d.classList.remove('knock'),650)},4200)}

// ---- таймер и бикфордов шнур ----
const fuse=$('#fuse'),timer=$('#timer'),note=$('#timerNote');
if(fuse&&timer){
  const burn=document.createElement('i'),spark=document.createElement('i');burn.className='burn';spark.className='spark';fuse.prepend(burn,spark);
  const items=[...fuse.children].filter(x=>x.tagName==='LI'),total=(parseInt(timer.textContent,10)||60)*60;
  const fmt=s=>String(Math.floor(s/60)).padStart(2,'0')+':'+String(s%60).padStart(2,'0');
  const set=p=>{fuse.style.setProperty('--p',p);const h=fuse.clientHeight-24;items.forEach(li=>li.classList.toggle('lit',p*h+16>=li.offsetTop))};
  set(0);
  if(RM){set(1);fuse.classList.add('burnt')}
  else{let started=false;onView(fuse,v=>{if(!v||started)return;started=true;const t0=performance.now(),D=7000;
    (function step(t){const p=Math.min(1,(t-t0)/D);set(p);timer.textContent=fmt(Math.round(total*(1-p)));
      if(p<1)requestAnimationFrame(step);else{fuse.classList.add('burnt');timer.classList.add('out');if(note)note.textContent='Время вышло. А вы успеете выбраться?'}})(t0)},.35)}
}
})();
