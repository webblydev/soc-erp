import "./blatui.js";
import Alpine from "alpinejs";
import { registerBlatUI } from "./blatui-core.js";
import { registerCharts } from "./blatui-charts.js";

registerBlatUI(Alpine);
registerCharts(Alpine);
window.Alpine = Alpine;
Alpine.start();
