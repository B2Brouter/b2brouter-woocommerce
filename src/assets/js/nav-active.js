// Highlights the nav link of the section currently in view.
// Watches the sections referenced by each .nav-links a[href^="#"] anchor
// using IntersectionObserver, and toggles the .is-active class
// on the matching link.

document.addEventListener('DOMContentLoaded', () => {
  // Hamburger toggle
  const hamburger = document.querySelector('.nav-hamburger');
  const navMenu = document.querySelector('.nav-links');
  if (hamburger && navMenu) {
    hamburger.addEventListener('click', () => {
      const open = navMenu.classList.toggle('is-open');
      hamburger.classList.toggle('is-open', open);
      hamburger.setAttribute('aria-expanded', open);
    });
    document.addEventListener('click', (e) => {
      if (!e.target.closest('.nav')) {
        navMenu.classList.remove('is-open');
        hamburger.classList.remove('is-open');
        hamburger.setAttribute('aria-expanded', 'false');
      }
    });
    navMenu.querySelectorAll('a').forEach(a => {
      a.addEventListener('click', () => {
        navMenu.classList.remove('is-open');
        hamburger.classList.remove('is-open');
        hamburger.setAttribute('aria-expanded', 'false');
      });
    });
  }

  const navLinks = [...document.querySelectorAll('.nav-links a[href^="#"]')];
  const sections = navLinks
    .map(a => {
      const id = a.getAttribute('href').slice(1);
      const el = document.getElementById(id);
      return el ? { el, link: a } : null;
    })
    .filter(Boolean);

  if (!sections.length) return;

  const setActive = (link) => {
    navLinks.forEach(a => a.classList.toggle('is-active', a === link));
  };

  const io = new IntersectionObserver((entries) => {
    // Pick the section closest to the top of the viewport that is in view
    const visible = entries
      .filter(e => e.isIntersecting)
      .sort((a, b) => a.target.getBoundingClientRect().top - b.target.getBoundingClientRect().top);
    if (visible.length) {
      const match = sections.find(s => s.el === visible[0].target);
      if (match) setActive(match.link);
    }
  }, { rootMargin: '-30% 0px -60% 0px', threshold: 0 });

  sections.forEach(s => io.observe(s.el));
});
