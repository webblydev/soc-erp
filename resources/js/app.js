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
