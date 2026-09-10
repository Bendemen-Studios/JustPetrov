(()=>{
  const start=()=>{
    document.querySelectorAll('.worked-slider').forEach(slider=>{
      const track=slider.querySelector('.worked-track');
      if(!track||track.dataset.jsMarquee==='true')return;
      track.dataset.jsMarquee='true';
      track.style.animation='none';
      track.style.animationPlayState='running';
      track.style.transform='translate3d(0,0,0)';

      const items=[...track.children];
      if(items.length<2)return;
      const firstSetCount=Math.max(1,Math.floor(items.length/2));
      const firstSet=items.slice(0,firstSetCount);
      const gap=parseFloat(getComputedStyle(track).columnGap||getComputedStyle(track).gap)||0;
      let loopWidth=0;
      const measure=()=>{
        loopWidth=firstSet.reduce((sum,item)=>sum+item.getBoundingClientRect().width,0)+Math.max(0,firstSetCount-1)*gap+gap;
      };
      measure();
      if(loopWidth<=0)return;

      let x=0,last=performance.now();
      const speed=45;
      const frame=now=>{
        const dt=Math.min(50,now-last)/1000;
        last=now;
        if(!document.hidden){
          x-=speed*dt;
          if(x<=-loopWidth)x+=loopWidth;
          track.style.transform=`translate3d(${x}px,0,0)`;
        }
        requestAnimationFrame(frame);
      };
      requestAnimationFrame(frame);
      window.addEventListener('resize',measure,{passive:true});
    });
  };
  if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',start,{once:true});
  else start();
})();
