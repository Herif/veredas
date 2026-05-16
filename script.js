const WHATSAPP_NUMBER = "5562981585703";

const header = document.querySelector("[data-header]");
const nav = document.querySelector("[data-nav]");
const navToggle = document.querySelector("[data-nav-toggle]");
const whatsappLinks = document.querySelectorAll("[data-whatsapp]");
const form = document.querySelector("[data-lead-form]");
const heroVideo = document.querySelector("[data-hero-video]");
const videoToggle = document.querySelector("[data-video-toggle]");

function updateHeader() {
  header.classList.toggle("is-scrolled", window.scrollY > 24);
}

function buildWhatsAppUrl(message) {
  const fallback = "#contato";
  if (!WHATSAPP_NUMBER) return fallback;
  const cleanNumber = WHATSAPP_NUMBER.replace(/\D/g, "");
  return `https://wa.me/${cleanNumber}?text=${encodeURIComponent(message)}`;
}

window.addEventListener("scroll", updateHeader, { passive: true });
updateHeader();

navToggle.addEventListener("click", () => {
  const isOpen = nav.classList.toggle("is-open");
  header.classList.toggle("is-open", isOpen);
  navToggle.setAttribute("aria-label", isOpen ? "Fechar menu" : "Abrir menu");
});

nav.addEventListener("click", (event) => {
  if (event.target.matches("a")) {
    nav.classList.remove("is-open");
    header.classList.remove("is-open");
  }
});

if (heroVideo && videoToggle) {
  videoToggle.addEventListener("click", () => {
    if (heroVideo.paused) {
      heroVideo.play();
      videoToggle.textContent = "Pausar video";
      videoToggle.setAttribute("aria-pressed", "false");
    } else {
      heroVideo.pause();
      videoToggle.textContent = "Reproduzir video";
      videoToggle.setAttribute("aria-pressed", "true");
    }
  });
}

whatsappLinks.forEach((link) => {
  link.addEventListener("click", (event) => {
    const url = buildWhatsAppUrl("Ola, tenho interesse no Veredas do Araguaia.");
    if (url === "#contato") return;
    event.preventDefault();
    window.open(url, "_blank", "noopener,noreferrer");
  });
});

form.addEventListener("submit", (event) => {
  event.preventDefault();
  const data = new FormData(form);
  const lead = {
    nome: data.get("nome") || "",
    telefone: data.get("telefone") || "",
    cidade: data.get("cidade") || "",
    interesse: data.get("interesse") || "",
    mensagem: data.get("mensagem") || "",
    origem: "site",
  };
  const message = [
    "Ola, tenho interesse no Veredas do Araguaia.",
    "",
    `Nome: ${lead.nome}`,
    `Telefone: ${lead.telefone}`,
    `Cidade: ${lead.cidade}`,
    `Interesse: ${lead.interesse}`,
    `Mensagem: ${lead.mensagem}`,
  ].join("\n");

  const submitButton = form.querySelector('button[type="submit"]');
  const originalText = submitButton.textContent;
  submitButton.disabled = true;
  submitButton.textContent = "Enviando...";

  fetch("api/leads.php", {
    method: "POST",
    headers: {
      "Content-Type": "application/json",
    },
    body: JSON.stringify(lead),
  })
    .then((response) => {
      if (!response.ok) throw new Error("Nao foi possivel salvar o lead.");
      return response.json();
    })
    .then((result) => {
      if (!result.ok) throw new Error("Nao foi possivel salvar o lead.");
      form.reset();
      const url = buildWhatsAppUrl(message);
      if (url !== "#contato") {
        window.open(url, "_blank", "noopener,noreferrer");
      }
      alert("Cadastro recebido com sucesso.");
    })
    .catch(() => {
      alert("Nao foi possivel salvar o cadastro agora. Tente novamente ou fale pelo WhatsApp.");
    })
    .finally(() => {
      submitButton.disabled = false;
      submitButton.textContent = originalText;
    });
});
