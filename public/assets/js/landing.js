(() => {
  const fallback = document.querySelector(".video-fallback");
  const video = document.querySelector("video[data-hero-video]");
  if (!video || !fallback) return;

  video.addEventListener("error", () => {
    fallback.style.display = "block";
  });
})();
