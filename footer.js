(()=>{
const links={
 'eywamc.png':'https://eywamc.com',
 'h20esports.png':'https://h20.gg/',
 'diehardsmp.png':'https://discord.diehardsmp.com',
 'stichtingsuperhelden.png':'https://www.stichtingsuperhelden.nl/',
 'steampunksmp.png':'https://dc.steampunksmp.com'
};
const style=document.createElement('style');
style.textContent=`
footer .contact a{color:var(--gold-bright)!important;text-decoration:none}
footer .contact a:visited{color:var(--gold-bright)!important}
.worked-slide>a{display:flex;width:100%;height:100%;align-items:center;justify-content:center;text-decoration:none}
.worked-slide>a img{cursor:pointer}
.worked-with-title{width:100%;max-width:100%;white-space:normal!important;overflow-wrap:anywhere!important;word-break:break-word!important;line-height:1.6!important}
.worked-slide{contain:layout paint}
.mobile-menu-toggle{display:none!important}
.nav nav>a,.nav nav>a.active{background:transparent!important;box-shadow:none!important;border:0!important}
.nav nav>a:before{content:none!important;display:none!important}
.nav nav>a.active:after{content:''!important;display:block!important;position:absolute!important;left:0!important;right:0!important;bottom:2px!important;width:auto!important;height:2px!important;background:var(--gold)!important;box-shadow:0 0 10px rgba(167,134,69,.32)!important;border:0!important;border-radius:0!important}
.projects-dropdown{position:relative;display:flex;align-items:center;height:100%}
.projects-dropdown-menu{position:absolute;top:100%;left:50%;transform:translateX(-50%) translateY(-2px);display:flex;flex-direction:column;min-width:150px;padding:10px 6px 6px;opacity:0;visibility:hidden;pointer-events:none;border:1px solid var(--line);background:rgba(7,6,4,.98);box-shadow:0 18px 40px rgba(0,0,0,.4);z-index:1100;transition:opacity .16s ease,transform .16s ease,visibility .16s ease}
.projects-dropdown-menu:before{content:'';position:absolute;left:0;right:0;top:-10px;height:10px}
.projects-dropdown-menu a{display:flex!important;align-items:center;justify-content:center;padding:11px 14px!important;color:#ddd!important;text-decoration:none;font:700 10px 'Space Mono',monospace;letter-spacing:.08em;white-space:nowrap}
.projects-dropdown-menu a:hover,.projects-dropdown-menu a.active{color:var(--gold-bright)!important;background:rgba(167,134,69,.08)}
.projects-dropdown-menu a:after,.projects-dropdown-menu a:before{content:none!important;display:none!important}
.projects-dropdown:hover .projects-dropdown-menu,.projects-dropdown:focus-within .projects-dropdown-menu{opacity:1;visibility:visible;pointer-events:auto;transform:translateX(-50%) translateY(0)}
.dropdown-chevron{font-size:9px;margin-left:5px}
.projects-mobile-arrow{display:none}
.nav nav .projects-dropdown>a{height:100%;display:flex;align-items:center;text-decoration:none}
@media(max-width:900px){.projects-dropdown>a{padding-right:48px!important}.projects-mobile-arrow{display:flex!important;position:absolute!important;right:0!important;top:0!important;width:42px!important;height:52px!important;align-items:center!important;justify-content:center!important;padding:0!important;border:0!important;border-bottom:1px solid var(--line-soft)!important;background:transparent!important;color:var(--gold-bright)!important;font:700 15px/1 'Space Mono',monospace!important;cursor:pointer!important;z-index:2}.projects-mobile-arrow:after{content:'⌄';transition:transform .2s ease}.projects-dropdown.open .projects-mobile-arrow:after{transform:rotate(180deg)}}
.worked-slide img[src*="stichtingsuperhelden.png"]{width:180px;max-width:180px;height:70px;max-height:70px;object-fit:contain;object-position:center;background:transparent;mix-blend-mode:screen}
.worked-slide img[src*="stichtingsuperhelden.png"]:hover{transform:scale(1.04)}
@media(max-width:900px){
 html,body{max-width:100%;overflow-x:hidden}
 .nav{height:72px!important;padding:0 18px!important;display:block!important}
 .nav .brand{position:absolute!important;left:50%!important;top:47%!important;transform:translate(-50%,-50%)!important;width:150px!important;height:auto!important;margin:0!important;padding:0!important;display:flex!important;justify-content:center!important}
 .nav .brand img{display:block!important;width:150px!important;height:auto!important;max-width:none!important;object-fit:contain!important;object-position:center!important;transform:none!important}
 .nav nav{display:none!important}
 .mobile-menu-toggle{position:absolute!important;left:18px!important;top:50%!important;transform:translateY(-50%)!important;z-index:1002!important;display:flex!important;flex-direction:column!important;justify-content:center!important;gap:5px!important;width:42px!important;height:42px!important;padding:10px!important;border:1px solid var(--line)!important;background:rgba(7,6,4,.96)!important;cursor:pointer!important}
 .mobile-menu-toggle span{display:block!important;width:20px!important;height:2px!important;background:var(--gold-bright)!important}
 .mobile-menu-toggle[aria-expanded=true] span:nth-child(1){transform:translateY(7px) rotate(45deg)}
 .mobile-menu-toggle[aria-expanded=true] span:nth-child(2){opacity:0}
 .mobile-menu-toggle[aria-expanded=true] span:nth-child(3){transform:translateY(-7px) rotate(-45deg)}
 .mobile-menu-backdrop{position:fixed!important;inset:0!important;background:rgba(0,0,0,.7)!important;backdrop-filter:blur(3px)!important;z-index:999!important;opacity:0!important;visibility:hidden!important;transition:opacity .25s,visibility .25s}
 .mobile-menu-backdrop.open{opacity:1!important;visibility:visible!important}
 .nav nav.mobile-menu{position:fixed!important;top:0!important;left:0!important;right:auto!important;width:min(82vw,340px)!important;height:100dvh!important;margin:0!important;padding:105px 30px 40px!important;display:flex!important;flex-direction:column!important;align-items:stretch!important;justify-content:flex-start!important;gap:4px!important;background:rgba(7,6,4,.98);border-right:1px solid var(--line);transform:translateX(-100%);transition:transform .3s cubic-bezier(.22,.61,.36,1);overflow-y:auto;z-index:1000}
 .nav nav.mobile-menu.open{transform:translateX(0)}
 .nav nav.mobile-menu a{height:auto!important;padding:16px 0!important;font-size:12px!important;border-bottom:1px solid var(--line-soft);justify-content:flex-start}
 .nav nav.mobile-menu a.active:after{left:0!important;right:auto!important;width:38px!important;bottom:7px!important}
 .nav.mobile-open{z-index:1001!important}body.menu-open{overflow:hidden}
 .projects-dropdown{display:block!important;width:100%;height:auto!important}
 .projects-dropdown>a{width:100%;box-sizing:border-box}
 .projects-dropdown-menu{position:static;transform:none!important;width:100%;min-width:0;box-sizing:border-box;margin:0;padding:0;display:block;max-height:0;overflow:hidden;opacity:0;visibility:hidden;pointer-events:none;border:0;box-shadow:none;background:transparent;transition:max-height .25s ease,opacity .2s ease,padding .25s ease}
 .projects-dropdown.open .projects-dropdown-menu{max-height:80px;padding:3px 0 4px 14px;opacity:1;visibility:visible;pointer-events:auto}
 .projects-dropdown.open .projects-dropdown-menu a{border-bottom:0!important;padding:12px 0!important;font-size:11px!important}
 .worked-with{padding:22px 0 8px;min-width:0!important;width:100%!important;max-width:100%!important}
 .worked-with-title{font-size:9px!important;letter-spacing:.13em!important;white-space:normal!important;line-height:1.65!important;max-width:100%!important;width:100%!important;padding-right:4px;box-sizing:border-box}
 .worked-slider{height:92px}.worked-track{gap:18px;animation-duration:22s}.worked-slide{width:145px;height:72px;opacity:1}.worked-slide img{max-width:135px;max-height:56px}.worked-slide img[src*="stichtingsuperhelden.png"]{width:135px;max-width:135px;height:56px;max-height:56px}
 main,footer{width:calc(100% - 36px);max-width:100%}
 footer{grid-template-columns:minmax(0,1fr)!important;gap:0;padding-bottom:36px!important;min-width:0!important;overflow:hidden!important}
 footer .footer-meta{text-align:left!important;padding-top:6px!important}
 footer .contact{margin-top:16px;display:flex;flex-direction:column;align-items:flex-start;gap:10px 20px;min-width:0!important;max-width:100%!important}
 footer .contact a,footer .contact span{overflow-wrap:anywhere!important;word-break:break-word!important;line-height:1.55!important;max-width:100%!important}
 .page-hero{padding-top:150px!important;min-height:auto!important;padding-bottom:72px!important}
 .page-content{margin:40px 0 70px!important}
 .links a,.links>div{align-items:flex-start;flex-direction:column;gap:7px;padding:17px 0;min-width:0!important;width:100%!important;max-width:100%!important;overflow:hidden}
 .links strong,.links span{min-width:0!important;max-width:100%!important;width:100%;overflow-wrap:anywhere!important;word-break:break-word!important;white-space:normal!important;line-height:1.55!important}
 .skill-levels{grid-template-columns:1fr 1fr}.skills{grid-template-columns:1fr}.skill{min-width:0;padding:20px 18px}.skill-top{min-width:0;flex-wrap:wrap;gap:10px}.skill-info{min-width:0;flex:1 1 190px}.skill strong{overflow-wrap:anywhere}.skill b{flex:0 0 auto}
}
@media(max-width:480px){
 main,footer{width:calc(100% - 28px)}.nav .brand{width:140px}.nav .brand img{width:140px}.mobile-menu-toggle{left:14px}.page-hero{padding-top:142px}.page-hero h1{font-size:clamp(62px,18vw,105px)}
 .worked-slide{width:125px!important}.worked-slide img{max-width:120px!important}.worked-slide img[src*="stichtingsuperhelden.png"]{width:120px;max-width:120px;height:56px;max-height:56px}.worked-slider:before,.worked-slider:after{width:20%}
}
@media(prefers-reduced-motion:reduce){.worked-track{animation:none;transform:none}}
`;
document.head.appendChild(style);
const cookieStyle=document.createElement('link');cookieStyle.rel='stylesheet';cookieStyle.href='/cookie.css?v=20260910';document.head.appendChild(cookieStyle);

document.querySelectorAll('.footer-meta').forEach(el=>{const text=el.textContent.trim();el.textContent=text.replace(/\b20\d{2}\b\s*$/,'')+' '+new Date().getFullYear()});
document.querySelectorAll('.worked-with-title').forEach(el=>{el.textContent='I have worked with companies, servers & individuals such as:'});

document.querySelectorAll('.worked-slider').forEach(slider=>{
 if(slider.dataset.marqueeReady)return;
 const slides=[...slider.querySelectorAll('.worked-slide')];
 if(!slides.some(s=>s.querySelector('img[src*="diehardsmp.png"]'))){const d=document.createElement('div');d.className='worked-slide';d.innerHTML='<img src="/assets/workedwith/diehardsmp.png" alt="Diehard SMP">';slides.push(d)}
 if(!slides.length)return;
 slides.forEach(slide=>{
  const img=slide.querySelector('img');
  if(!img)return;
  const key=((img.getAttribute('src')||'').split('/').pop().split('?')[0].split('#')[0]).toLowerCase();
  const href=links[key];
  if(href&&!slide.querySelector('a')){const a=document.createElement('a');a.href=href;a.target='_blank';a.rel='noopener noreferrer';a.setAttribute('aria-label','Open '+(img.alt||key));img.parentNode.insertBefore(a,img);a.appendChild(img)}
 });
 const track=document.createElement('div');track.className='worked-track';
 const base=slides.map(slide=>slide.cloneNode(true));
 const addSet=(source,hidden=false)=>source.forEach(slide=>{const item=slide.cloneNode(true);if(hidden)item.setAttribute('aria-hidden','true');track.appendChild(item)});
 addSet(slides);addSet(slides,true);
 const fillLoop=()=>{let sets=2;while(track.scrollWidth<slider.clientWidth*2){addSet(base,true);sets++}if(sets%2)addSet(base,true)};
  slider.replaceChildren(track);fillLoop();
 const canHover=()=>window.matchMedia('(hover:hover) and (pointer:fine)').matches;
 if(canHover())track.style.animationPlayState='running';
 track.addEventListener('pointerenter',e=>{if(canHover()&&e.target.closest('.worked-slide>a'))track.style.animationPlayState='paused'});
 track.addEventListener('pointerleave',e=>{if(canHover()&&e.target.closest('.worked-slide>a'))track.style.animationPlayState='running'});
 slider.dataset.marqueeReady='true';
});

const nav=document.querySelector('.nav'),menu=document.querySelector('.nav nav');
if(menu&&!menu.querySelector('.projects-dropdown')){
 const projectsLink=menu.querySelector('a[href="/projects"]');
 if(projectsLink){
  const dropdown=document.createElement('div');dropdown.className='projects-dropdown';
  const current=location.pathname.replace(/\/+$/,'')||'/';
  if(current==='/projects')projectsLink.classList.add('active');
  const trigger=projectsLink.cloneNode(true);trigger.removeAttribute('class');trigger.setAttribute('aria-haspopup','true');trigger.setAttribute('aria-expanded','false');trigger.innerHTML='PROJECTS <span class="dropdown-chevron">▾</span>';
  const submenu=document.createElement('div');submenu.className='projects-dropdown-menu';
  const modrinth=document.createElement('a');modrinth.href='/modrinth';modrinth.textContent='MODRINTH';if(current==='/modrinth')modrinth.className='active';submenu.appendChild(modrinth);
  const arrow=document.createElement('button');arrow.type='button';arrow.className='projects-mobile-arrow';arrow.setAttribute('aria-label','Toggle Projects submenu');arrow.setAttribute('aria-expanded','false');dropdown.append(trigger,submenu,arrow);projectsLink.replaceWith(dropdown);
  const setOpen=open=>{dropdown.classList.toggle('open',open);arrow.setAttribute('aria-expanded',String(open));trigger.setAttribute('aria-expanded',String(open))};
  arrow.addEventListener('click',e=>{e.preventDefault();e.stopPropagation();setOpen(!dropdown.classList.contains('open'))});
  trigger.addEventListener('click',e=>{if(window.innerWidth<=900){e.preventDefault();location.href='/projects'}});
 }
}

const mobile=document.querySelector('.nav nav');
if(nav&&mobile&&!document.querySelector('.mobile-menu-toggle')){
 const button=document.createElement('button');button.className='mobile-menu-toggle';button.type='button';button.setAttribute('aria-label','Open menu');button.setAttribute('aria-expanded','false');button.innerHTML='<span></span><span></span><span></span>';
 const backdrop=document.createElement('div');backdrop.className='mobile-menu-backdrop';document.body.appendChild(backdrop);mobile.classList.add('mobile-menu');nav.appendChild(button);
 const close=()=>{mobile.classList.remove('open');backdrop.classList.remove('open');nav.classList.remove('mobile-open');document.body.classList.remove('menu-open');button.setAttribute('aria-expanded','false');button.setAttribute('aria-label','Open menu');const pd=mobile.querySelector('.projects-dropdown');if(pd){pd.classList.remove('open');const t=pd.querySelector(':scope>a');if(t)t.setAttribute('aria-expanded','false');const ar=pd.querySelector('.projects-mobile-arrow');if(ar)ar.setAttribute('aria-expanded','false')}};
 const open=()=>{mobile.classList.add('open');backdrop.classList.add('open');nav.classList.add('mobile-open');document.body.classList.add('menu-open');button.setAttribute('aria-expanded','true');button.setAttribute('aria-label','Close menu')};
 button.addEventListener('click',()=>button.getAttribute('aria-expanded')==='true'?close():open());backdrop.addEventListener('click',close);mobile.querySelectorAll('a').forEach(a=>a.addEventListener('click',e=>{if(a.closest('.projects-dropdown')&&!a.closest('.projects-dropdown-menu'))return;close()}));window.addEventListener('keydown',e=>{if(e.key==='Escape')close()});window.addEventListener('resize',()=>{if(window.innerWidth>900)close()});
}

const getCookie=name=>document.cookie.split('; ').find(row=>row.startsWith(name+'='))?.split('=').slice(1).join('=');
const setCookie=(name,value,maxAge=31536000)=>{document.cookie=`${name}=${encodeURIComponent(value)}; Max-Age=${maxAge}; Path=/; SameSite=Lax`};
if(!getCookie('justpetrov_cookie_consent')){const box=document.createElement('section');box.className='cookie-consent';box.setAttribute('role','dialog');box.setAttribute('aria-label','Cookie consent');box.innerHTML='<h2>We love Cookies</h2><p>We gebruiken cookies om je cookievoorkeur te onthouden en de website goed te laten werken. Kies hieronder of je cookies wilt accepteren of weigeren.</p><div class="cookie-actions"><button class="cookie-accept" type="button">Accepteren</button><button class="cookie-reject" type="button">Weigeren</button></div>';document.body.appendChild(box);const finish=value=>{setCookie('justpetrov_cookie_consent',value);box.remove();window.dispatchEvent(new CustomEvent('justpetrov-cookie-consent',{detail:{consent:value}}))};box.querySelector('.cookie-accept').onclick=()=>finish('accepted');box.querySelector('.cookie-reject').onclick=()=>finish('rejected')}
})();

(()=>{
 const path=location.pathname.replace(/\/+$/,'')||'/';
 const pages={
 '/':{title:'JustPetrov — Together for a Better Tomorrow',description:'JustPetrov is a Dutch gamer, freelancer and CEO working across Minecraft, gaming, technology, web development and creative projects.',keywords:'JustPetrov, Dutch gamer, freelancer, CEO, Minecraft, gaming, technology, web development, Netherlands'},
 '/setup':{title:'JustPetrov — Gaming & Creator Setup',description:'Explore JustPetrov’s PC, peripherals, console, mobile and travel gaming setup.',keywords:'JustPetrov setup, gaming setup, PC gaming, Minecraft setup'},
 '/profiles':{title:'JustPetrov — Profiles & Social Links',description:'Find JustPetrov’s public GitHub, Discord, Spotify and Steam profiles.',keywords:'JustPetrov profiles, GitHub, Discord, Spotify, Steam'},
 '/projects':{title:'JustPetrov — Minecraft & Digital Projects',description:'Discover JustPetrov’s active, paused and finished Minecraft, gaming, web and digital projects.',keywords:'JustPetrov projects, Minecraft projects, HVMC, Steampunk SMP, EywaMC, Diehard SMP'},
 '/skills':{title:'JustPetrov — Skills & Expertise',description:'JustPetrov’s skills include Minecraft building, server management, modpacks, design, WordPress, coding and photography.',keywords:'JustPetrov skills, Minecraft builder, server manager, modpack maker, WordPress'},
 '/stats':{title:'JustPetrov — Spotify Music Stats',description:'Public Spotify listening statistics from JustPetrov.',keywords:'JustPetrov Spotify stats, Spotify listening stats'},
 '/organisations':{title:'JustPetrov — Organisations',description:'Organisations connected to JustPetrov.',keywords:'JustPetrov organisations, Bendemen Studios, Bendemen'},
 '/modrinth':{title:'JustPetrov — Modrinth Projects',description:'JustPetrov’s live Modrinth projects, including the Steampunk SMP Collection and custom Minecraft mods.',keywords:'JustPetrov Modrinth, Steampunk SMP Collection, Minecraft mods'}
 };
 const p=pages[path];if(!p)return;
 const canonical='https://justpetrov.com'+(path==='/'?'/' : path+'/');
 const setMeta=(name,content,attr='name')=>{let el=document.head.querySelector(`meta[${attr}="${name}"]`);if(!el){el=document.createElement('meta');el.setAttribute(attr,name);document.head.appendChild(el)}el.setAttribute('content',content)};
 document.title=p.title;setMeta('description',p.description);setMeta('keywords',p.keywords);setMeta('author','JustPetrov');setMeta('robots','index,follow,max-image-preview:large,max-snippet:-1,max-video-preview:-1');setMeta('og:type',path==='/'?'profile':'website','property');setMeta('og:site_name','JustPetrov','property');setMeta('og:title',p.title,'property');setMeta('og:description',p.description,'property');setMeta('og:url',canonical,'property');setMeta('og:image','https://justpetrov.com/assets/apple-touch-icon.png','property');setMeta('twitter:card','summary','name');setMeta('twitter:title',p.title,'name');setMeta('twitter:description',p.description,'name');setMeta('twitter:image','https://justpetrov.com/assets/apple-touch-icon.png','name');
 let canonicalLink=document.head.querySelector('link[rel="canonical"]');if(!canonicalLink){canonicalLink=document.createElement('link');canonicalLink.rel='canonical';document.head.appendChild(canonicalLink)}canonicalLink.href=canonical;
})();