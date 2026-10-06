import { registerBlatUI } from "./blatui-core.js";
import { registerCharts } from "./blatui-charts.js";

// Livewire bundles and starts Alpine; register BlatUI onto that instance (light-only).
document.addEventListener("alpine:init", () => {
    registerBlatUI(window.Alpine, { darkMode: false });
    registerCharts(window.Alpine);
});

// Drive the BlatUI top progress bar from wire:navigate transitions.
document.addEventListener("livewire:navigate", () => {
    window.dispatchEvent(new CustomEvent("top-progress:start"));
});
document.addEventListener("livewire:navigated", () => {
    window.dispatchEvent(new CustomEvent("top-progress:done"));
});

// At md and up, links marked data-detail-modal open in the detail modal instead of navigating
// (DetailModal). Capture-phase listeners run before wire:navigate's own link listeners.
const isDesktop = window.matchMedia("(min-width: 768px)");
const isPlainPress = (e) => !(e.altKey || e.ctrlKey || e.metaKey || e.shiftKey);
const detailLink = (e) => (isDesktop.matches && isPlainPress(e) ? e.target.closest?.("a[data-detail-modal][href]") : null);

window.addEventListener("mousedown", (e) => {
    if (e.button === 0 && detailLink(e)) {
        e.stopPropagation();
    }
}, true);
window.addEventListener("keydown", (e) => {
    if (e.key === "Enter" && detailLink(e)) {
        e.stopPropagation();
    }
}, true);
window.addEventListener("click", (e) => {
    const link = e.button === 0 ? detailLink(e) : null;

    if (!link) {
        return;
    }

    e.preventDefault();
    if (!e.isTrusted) {
        e.stopPropagation();
    }
    window.dispatchEvent(new CustomEvent("open-detail-modal", { detail: { url: link.href } }));
}, true);
document.addEventListener("livewire:navigate", () => {
    window.dispatchEvent(new CustomEvent("close-dialog-detail-modal"));
});
