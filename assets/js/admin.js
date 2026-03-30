/**
 * PersonalPortal — Admin Panel JavaScript
 */

// ── Colour Palette Swatches ───────────────────────────────────────────────────
(function () {
  function initPalette() {
    // Swatch click → update colour input + mark active
    document.querySelectorAll('.color-swatch').forEach(function (btn) {
      btn.addEventListener('click', function () {
        var color    = this.dataset.color;
        var targetId = this.dataset.target;
        var input    = document.getElementById(targetId);
        if (!input) return;

        input.value = color;
        syncDisplay(targetId, color);
        markActive(this);
      });
    });

    // Manual colour-picker change → sync display label + deselect mismatched swatches
    document.querySelectorAll('input.color-input[type="color"]').forEach(function (input) {
      input.addEventListener('input', function () {
        syncDisplay(this.id, this.value);
        // Highlight the matching swatch if value matches one of the palette colours
        var swatches = document.querySelectorAll('.color-swatch[data-target="' + this.id + '"]');
        swatches.forEach(function (s) {
          s.classList.toggle('active', s.dataset.color.toLowerCase() === input.value.toLowerCase());
        });
      });
    });
  }

  function markActive(clickedBtn) {
    var targetId = clickedBtn.dataset.target;
    document.querySelectorAll('.color-swatch[data-target="' + targetId + '"]')
            .forEach(function (s) { s.classList.remove('active'); });
    clickedBtn.classList.add('active');
  }

  function syncDisplay(inputId, color) {
    var display = document.getElementById(inputId + '_display');
    if (display) display.textContent = color;
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initPalette);
  } else {
    initPalette();
  }
})();
