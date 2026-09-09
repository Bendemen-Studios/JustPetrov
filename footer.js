document.querySelectorAll('.worked-slider').forEach(slider => {
  const slides = [...slider.querySelectorAll('.worked-slide')];
  if (slides.length < 2) return;
  let index = 0;
  let timer;
  const show = next => {
    slides[index].classList.remove('active');
    index = (next + slides.length) % slides.length;
    slides[index].classList.add('active');
  };
  const start = () => {
    timer = window.setInterval(() => show(index + 1), 2800);
  };
  const stop = () => window.clearInterval(timer);
  slider.addEventListener('mouseenter', stop);
  slider.addEventListener('mouseleave', start);
  start();
});