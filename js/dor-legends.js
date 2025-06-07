// Initialize Hammer.js for double tap handling
document.addEventListener("DOMContentLoaded", function () {
  const legendsModal = document.getElementById("legendsModal");
  const modalHeader = document.getElementById("legendsModalHeader");
  const modalBody = document.getElementById("legendsModalBody");
  const bsModal = new bootstrap.Modal(legendsModal);

  // Configure Hammer.js for modal header
  const headerHammer = new Hammer(modalHeader);
  headerHammer.add(
    new Hammer.Tap({
      event: "doubletap",
      taps: 2,
      interval: 400,
      threshold: 10,
    })
  );

  // Configure Hammer.js for modal body
  const bodyHammer = new Hammer(modalBody);
  bodyHammer.add(
    new Hammer.Tap({
      event: "doubletap",
      taps: 2,
      interval: 400,
      threshold: 10,
    })
  );

  // Handle double tap on modal header
  headerHammer.on("doubletap", function (e) {
    bsModal.hide();
  });

  // Handle double tap on modal body
  bodyHammer.on("doubletap", function (e) {
    bsModal.hide();
  });

  // Ensure backdrop and modal-open class are removed
  legendsModal.addEventListener("hidden.bs.modal", function () {
    const backdrop = document.querySelector(".modal-backdrop");
    if (backdrop) {
      backdrop.remove();
    }
    document.body.classList.remove("modal-open");
    document.body.style.overflow = "";
    document.body.style.paddingRight = "";
  });
});
