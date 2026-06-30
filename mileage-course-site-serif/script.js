const menuBtn = document.querySelector('.menu-btn');
const nav = document.querySelector('.nav');

menuBtn?.addEventListener('click', () => {
  const isOpen = nav.classList.toggle('show');
  menuBtn.setAttribute('aria-expanded', String(isOpen));
});

document.querySelectorAll('.nav a').forEach(link => {
  link.addEventListener('click', () => {
    nav.classList.remove('show');
    menuBtn?.setAttribute('aria-expanded', 'false');
  });
});

document.querySelectorAll('.module-title').forEach(button => {
  button.addEventListener('click', () => {
    const module = button.closest('.module');
    const isOpen = module.classList.toggle('open');
    button.setAttribute('aria-expanded', String(isOpen));
  });
});
