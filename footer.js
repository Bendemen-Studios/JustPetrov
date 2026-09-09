document.querySelectorAll('.worked-slider').forEach(slider => {
  const slides = [...slider.querySelectorAll('.worked-slide')];
  if (!slides.length || slider.querySelector('.worked-track')) return;

  const track = document.createElement('div');
  track.className = 'worked-track';

  slides.forEach(slide => track.appendChild(slide));
  slides.forEach(slide => track.appendChild(slide.cloneNode(true)));
  slider.appendChild(track);
});
