package tn.unidar.desktop.utils;

import java.util.Base64;

public class SvgUtils {

    /**
     * Generates a base64 encoded SVG placeholder.
     */
    public static String getPlaceholder(String title, String type) {
        String color = getPathColor(type);
        String icon = getIcon(type);
        
        String svg = "<svg width='400' height='300' viewBox='0 0 400 300' xmlns='http://www.w3.org/2000/svg'>" +
                     "  <rect width='400' height='300' fill='#f1f5f9'/>" +
                     "  <defs>" +
                     "    <linearGradient id='grad' x1='0%' y1='0%' x2='100%' y2='100%'>" +
                     "      <stop offset='0%' style='stop-color:#f1f5f9;stop-opacity:1' />" +
                     "      <stop offset='100%' style='stop-color:#e2e8f0;stop-opacity:1' />" +
                     "    </linearGradient>" +
                     "  </defs>" +
                     "  <rect width='400' height='300' fill='url(#grad)'/>" +
                     "  <circle cx='200' cy='130' r='50' fill='" + color + "' fill-opacity='0.1'/>" +
                     "  <text x='200' y='145' font-family='Arial' font-size='40' text-anchor='middle' fill='" + color + "'>" + icon + "</text>" +
                     "  <text x='200' y='210' font-family='Arial' font-size='14' font-weight='bold' text-anchor='middle' fill='#64748b'>" + title + "</text>" +
                     "  <text x='200' y='230' font-family='Arial' font-size='11' text-anchor='middle' fill='#94a3b8'>UNIDAR Verified Property</text>" +
                     "</svg>";
                     
        return "data:image/svg+xml;base64," + Base64.getEncoder().encodeToString(svg.getBytes());
    }

    private static String getPathColor(String type) {
        if (type == null) return "#4f46e5";
        return switch (type.toLowerCase()) {
            case "apartment" -> "#4f46e5"; // Indigo
            case "studio"    -> "#06b6d4"; // Cyan
            case "villa"     -> "#8b5cf6"; // Violet
            case "room"      -> "#10b981"; // Emerald
            case "shared"    -> "#f59e0b"; // Amber
            default          -> "#6366f1";
        };
    }

    private static String getIcon(String type) {
        if (type == null) return "🏠";
        return switch (type.toLowerCase()) {
            case "apartment" -> "🏢";
            case "studio"    -> "🏠";
            case "villa"     -> "🏰";
            case "room"      -> "🛏";
            case "shared"    -> "🤝";
            default          -> "🏠";
        };
    }
}
