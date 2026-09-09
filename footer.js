document.querySelectorAll('.worked-slider').forEach(slider => {
  if (slider.dataset.marqueeReady) return;
  const slides = [...slider.querySelectorAll('.worked-slide')];
  if (!slides.length) return;

  const track = document.createElement('div');
  track.className = 'worked-track';

  slides.forEach(slide => track.appendChild(slide));
  slides.forEach(slide => {
    const clone = slide.cloneNode(true);
    clone.setAttribute('aria-hidden', 'true');
    track.appendChild(clone);
  });

  slider.replaceChildren(track);
  slider.dataset.marqueeReady = 'true';
});
