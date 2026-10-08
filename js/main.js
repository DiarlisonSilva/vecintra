document.documentElement.classList.add("js");

const WHATSAPP = "5511988910821";

const reveals = document.querySelectorAll(".reveal");
if (!("IntersectionObserver" in window)) {
  reveals.forEach((el) => el.classList.add("is-in"));
} else {
  const observer = new IntersectionObserver((entries) => {
    entries.forEach((entry) => {
      if (!entry.isIntersecting) return;
      entry.target.classList.add("is-in");
      observer.unobserve(entry.target);
    });
  }, { threshold: 0.18, rootMargin: "0px 0px -8% 0px" });
  reveals.forEach((el) => observer.observe(el));
}

const header = document.querySelector(".header");
const progress = document.querySelector(".progress");
const menuBtn = document.querySelector(".menu-btn");
const nav = document.querySelector(".nav");
const loader = document.querySelector(".loader");
const form = document.querySelector("#diagnostico");
const lightbox = document.querySelector("#lightbox");

function onScroll() {
  const scrolled = window.scrollY;
  header?.classList.toggle("is-solid", scrolled > 24);
  const max = document.documentElement.scrollHeight - window.innerHeight;
  if (progress && max > 0) progress.style.width = `${(scrolled / max) * 100}%`;
}

window.addEventListener("scroll", onScroll, { passive: true });
onScroll();

menuBtn?.addEventListener("click", () => {
  const open = header.classList.toggle("is-open");
  menuBtn.setAttribute("aria-expanded", String(open));
  document.body.style.overflow = open ? "hidden" : "";
});

nav?.addEventListener("click", (event) => {
  if (event.target.closest("a")) {
    header.classList.remove("is-open");
    menuBtn?.setAttribute("aria-expanded", "false");
    document.body.style.overflow = "";
  }
});

window.addEventListener("load", () => loader?.classList.add("is-off"));
setTimeout(() => loader?.classList.add("is-off"), 2200);

const shots = [...document.querySelectorAll(".shot")];
let shotIndex = 0;

function showShot(index) {
  if (!lightbox || !shots.length) return;
  shotIndex = (index + shots.length) % shots.length;
  const shot = shots[shotIndex];
  lightbox.querySelector("img").src = shot.dataset.full;
  lightbox.querySelector("img").alt = shot.dataset.caption || "";
  lightbox.querySelector("figcaption").textContent = shot.dataset.caption || "";
  if (!lightbox.open) lightbox.showModal();
}

shots.forEach((shot, index) => {
  shot.addEventListener("click", () => showShot(index));
});

lightbox?.querySelector(".lightbox__close")?.addEventListener("click", () => lightbox.close());
lightbox?.querySelector(".lightbox__nav--prev")?.addEventListener("click", () => showShot(shotIndex - 1));
lightbox?.querySelector(".lightbox__nav--next")?.addEventListener("click", () => showShot(shotIndex + 1));
lightbox?.addEventListener("click", (event) => {
  if (event.target === lightbox) lightbox.close();
});
document.addEventListener("keydown", (event) => {
  if (!lightbox?.open) return;
  if (event.key === "ArrowRight") showShot(shotIndex + 1);
  if (event.key === "ArrowLeft") showShot(shotIndex - 1);
});

function whatsappLink(data) {
  const text = [
    "Olá, VECINTRA. Quero solicitar um diagnóstico.",
    "",
    `Nome: ${data.nome}`,
    `Telefone: ${data.telefone}`,
    `E-mail: ${data.email}`,
    `Imóvel: ${data.imovel}`,
    `Interesse: ${data.servico}`,
    data.mensagem ? `Mensagem: ${data.mensagem}` : "",
  ].filter(Boolean).join("\n");
  return `https://wa.me/${WHATSAPP}?text=${encodeURIComponent(text)}`;
}

function applyService(value) {
  const select = form?.querySelector("[name=servico]");
  if (select && value) select.value = value;
  document.querySelector("#contato")?.scrollIntoView({ behavior: "smooth" });
  form?.querySelector("[name=nome]")?.focus({ preventScroll: true });
}

document.querySelectorAll("[data-servico]").forEach((button) => {
  button.addEventListener("click", () => applyService(button.dataset.servico));
});

const params = new URLSearchParams(location.search);
if (params.get("servico")) applyService(params.get("servico"));

function showError(message) {
  const box = form.querySelector(".form__error");
  box.textContent = message;
  box.classList.add("is-on");
}

form?.addEventListener("submit", async (event) => {
  event.preventDefault();
  const error = form.querySelector(".form__error");
  error.classList.remove("is-on");
  const data = Object.fromEntries(new FormData(form));
  if (data.empresa_site) return;

  const nome = String(data.nome || "").trim();
  const telefone = String(data.telefone || "").trim();
  const email = String(data.email || "").trim();
  const servico = String(data.servico || "").trim();
  if (nome.length < 2) return showError("Informe seu nome.");
  if (telefone.replace(/\D/g, "").length < 10) return showError("Informe um telefone com DDD.");
  if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) return showError("Informe um e-mail válido.");
  if (!servico) return showError("Escolha o serviço de interesse.");

  const payload = {
    nome,
    telefone,
    email,
    imovel: data.imovel,
    servico,
    mensagem: String(data.mensagem || "").trim(),
    empresa_site: data.empresa_site || "",
  };

  const button = form.querySelector("[type=submit]");
  button.disabled = true;
  button.textContent = "Enviando…";

  let saved = false;
  let serverError = "";
  if (location.protocol !== "file:") {
    try {
      const response = await fetch("api/contato.php", {
        method: "POST",
        headers: { "Content-Type": "application/json", Accept: "application/json" },
        body: JSON.stringify(payload),
      });
      const body = await response.json().catch(() => ({}));
      if (response.ok && body.ok) saved = true;
      else if (response.status >= 400 && response.status < 500 && body.error) serverError = body.error;
    } catch {
      saved = false;
    }
  }

  button.disabled = false;
  button.textContent = "Enviar solicitação para a engenharia";
  if (serverError) return showError(serverError);

  const success = form.querySelector(".success");
  const title = success.querySelector("h3");
  const text = success.querySelector("p");
  success.querySelector("a").href = whatsappLink(payload);
  if (saved) {
    title.textContent = "Solicitação recebida.";
    text.textContent = "A equipe VECINTRA vai retornar pelo telefone ou e-mail informados. Se preferir, envie a mesma mensagem agora pelo WhatsApp.";
  } else {
    title.textContent = "Sua mensagem está pronta.";
    text.textContent = "Neste ambiente a página não grava o pedido no servidor. Abra o WhatsApp para enviar a solicitação direto para a VECINTRA.";
  }
  form.classList.add("is-done");
  success.classList.add("is-on");
});

form?.querySelector(".success button")?.addEventListener("click", () => {
  form.classList.remove("is-done");
  form.querySelector(".success").classList.remove("is-on");
  form.reset();
});
