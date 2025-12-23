document.addEventListener('DOMContentLoaded', () => {
  const covers = Array.from(document.querySelectorAll('.wp-block-cover.WPBlockCoverSlider'));
  if (!covers.length) return;

  covers.forEach((cover) => {
    const src = document.querySelector('.czik-hero-rotator__src');
    if (!src) return;

    const baseImg = cover.querySelector('.wp-block-cover__image-background');
    if (!baseImg) return;

    const imgs = Array.from(src.querySelectorAll('img'))
      .map(img => img.getAttribute('src'))
      .filter(Boolean);

    if (imgs.length < 2) return;

    let imgB = cover.querySelector('.czik-hero-rotator__img-b');
    if (!imgB) {
      imgB = document.createElement('img');
      imgB.className = 'czik-hero-rotator__img-b';
      imgB.alt = '';
      imgB.decoding = 'async';
      imgB.loading = 'eager';
      baseImg.insertAdjacentElement('afterend', imgB);
    }

    let i = 0;
    let prepared = false;

    const intervalMs = 10000;
    const fadeDelay = 950;

    setInterval(() => {
      i = (i + 1) % imgs.length;
      const nextUrl = imgs[i];

      const pre = new Image();
      pre.decoding = 'async';
      pre.onload = () => {
        if (!prepared) {
          baseImg.removeAttribute('srcset');
          baseImg.removeAttribute('sizes');
          baseImg.removeAttribute('fetchpriority');
          prepared = true;
        }

        imgB.src = nextUrl;
        imgB.style.opacity = '1';

        setTimeout(() => {
          baseImg.src = nextUrl;
          imgB.style.opacity = '0';
        }, fadeDelay);
      };
      pre.src = nextUrl;
    }, intervalMs);
  });
});
