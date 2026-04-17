package tn.unidar.desktop.utils;

import javafx.scene.Scene;
import javafx.scene.control.Button;
import javafx.scene.control.Label;
import javafx.scene.layout.HBox;
import javafx.scene.layout.Priority;
import javafx.scene.layout.Region;
import javafx.scene.layout.VBox;
import javafx.scene.web.WebView;
import javafx.stage.Modality;
import javafx.stage.Stage;
import org.json.JSONObject;
import tn.unidar.desktop.services.ApiClient;

public class ContractViewer {

    public static void show(int contractId) {
        Stage dialog = new Stage();
        dialog.initModality(Modality.APPLICATION_MODAL);
        dialog.setTitle(I18n.t("contract.view.title") + " #" + contractId);

        VBox root = new VBox(15);
        root.setStyle("-fx-padding: 20; -fx-background-color: #f8fafc;");
        root.setPrefWidth(720);
        root.setPrefHeight(860);

        // ── Header ──────────────────────────────────────────────────────
        HBox header = new HBox(10);
        header.setAlignment(javafx.geometry.Pos.CENTER_LEFT);
        header.setStyle("-fx-padding: 0 0 12 0; -fx-border-color: transparent transparent #e2e8f0 transparent; -fx-border-width: 0 0 1 0;");

        Label title = new Label("📄 " + I18n.t("contract") + " #" + contractId);
        title.setStyle("-fx-font-size: 18px; -fx-font-weight: bold; -fx-text-fill: #0f172a;");

        Region spacer = new Region();
        HBox.setHgrow(spacer, Priority.ALWAYS);

        Button btnClose = new Button("✕  Close");
        btnClose.getStyleClass().add("btn-ghost");
        btnClose.setOnAction(e -> dialog.close());
        header.getChildren().addAll(title, spacer, btnClose);

        // ── Loading Label ────────────────────────────────────────────────
        Label lblLoading = new Label(I18n.t("common.loading"));
        lblLoading.setStyle("-fx-text-fill: #64748b; -fx-padding: 20;");

        // ── WebView ──────────────────────────────────────────────────────
        WebView webView = new WebView();
        VBox.setVgrow(webView, Priority.ALWAYS);

        root.getChildren().addAll(header, lblLoading, webView);

        // ── Background load ──────────────────────────────────────────────
        new Thread(() -> {
            try {
                JSONObject resp = ApiClient.getInstance().get(
                        "/contracts.php?action=status&contract_id=" + contractId);

                if (!resp.optBoolean("success", true)) {
                    String err = resp.optString("error", "Contract not found.");
                    javafx.application.Platform.runLater(() -> lblLoading.setText("Error: " + err));
                    return;
                }

                JSONObject contract = resp.optJSONObject("contract");
                if (contract == null) {
                    String err = resp.optString("error", "Contract not found.");
                    javafx.application.Platform.runLater(() -> lblLoading.setText("Error: " + err));
                    return;
                }

                // Primary column is `content`; legacy DBs may expose `contract_content`
                String content = contract.optString("content", "");
                if (content.isEmpty()) content = contract.optString("contract_content", "");

                // Fetch signature images from PHP-compatible fields (paths)
                String studentSigPath = contract.optString("student_signature_path", "");
                if (studentSigPath.isEmpty()) studentSigPath = contract.optString("student_signature", "");
                
                String ownerSigPath = contract.optString("owner_signature_path", "");
                if (ownerSigPath.isEmpty()) ownerSigPath = contract.optString("owner_signature", "");

                // Resolve relative paths to full URLs or Proxy URLs
                String studentSigUrl = studentSigPath.isEmpty() ? "" : ApiClient.resolveImageUrl(studentSigPath);
                String ownerSigUrl   = ownerSigPath.isEmpty() ? "" : ApiClient.resolveImageUrl(ownerSigPath);

                // Build final HTML
                final String html;
                if (!content.isEmpty() && content.contains("<html")) {
                    // Existing full HTML — inject signature images if available
                    html = injectSignatures(content, studentSigUrl, ownerSigUrl);
                } else {
                    // Render clean structured view from contract fields
                    String studentName  = contract.optString("student_name",  "—");
                    String ownerName    = contract.optString("owner_name",    "—");
                    String listingTitle = contract.optString("listing_title", "—");
                    String startDate    = contract.optString("start_date",    "—");
                    String endDate      = contract.optString("end_date",      "—");
                    double rent         = contract.optDouble("monthly_rent",  0);
                    String status       = contract.optString("status",        "—");
                    String rawBody      = content.isEmpty()
                            ? "(No detailed content recorded for this contract.)"
                            : content.replace("\n", "<br>");

                    // Signature HTML fragments
                    String sigStudent = buildSigHtml(studentSigUrl, "Student Signature", studentName);
                    String sigOwner   = buildSigHtml(ownerSigUrl,   "Owner Signature",   ownerName);

                    html = "<!DOCTYPE html><html><head>"
                        + "<meta charset='UTF-8'>"
                        + "<style>"
                        + "  @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap');"
                        + "  * { box-sizing: border-box; margin: 0; padding: 0; }"
                        + "  body { font-family: 'Inter','Segoe UI',sans-serif; padding: 48px 64px;"
                        + "         color: #0f172a; line-height: 1.75; background: white; }"
                        + "  .brand-header { display:flex; align-items:center; gap:12px;"
                        + "                  border-bottom: 2px solid #4f46e5; padding-bottom: 20px; margin-bottom: 32px; }"
                        + "  .brand-logo   { font-size:28px; font-weight:800; color:#4f46e5; letter-spacing:-1px; }"
                        + "  .brand-sub    { font-size:12px; color:#94a3b8; margin-top:2px; }"
                        + "  .doc-title    { font-size:11px; font-weight:700; color:#64748b; text-transform:uppercase;"
                        + "                  letter-spacing:1px; margin-bottom:4px; }"
                        + "  .doc-id       { font-size:22px; font-weight:700; color:#0f172a; }"
                        + "  .meta-grid    { display:grid; grid-template-columns:1fr 1fr; gap:16px 40px;"
                        + "                  background:#f8fafc; border:1px solid #e2e8f0; border-radius:12px;"
                        + "                  padding:24px 28px; margin:28px 0; }"
                        + "  .meta-item label { display:block; font-size:10px; font-weight:700; color:#94a3b8;"
                        + "                     text-transform:uppercase; letter-spacing:.6px; margin-bottom:4px; }"
                        + "  .meta-item span  { font-size:14px; font-weight:600; color:#1e293b; }"
                        + "  .status-badge    { display:inline-block; padding:4px 14px; border-radius:20px;"
                        + "                     font-size:12px; font-weight:700; background:#fef9c3; color:#854d0e; }"
                        + "  .status-badge.active,.status-badge.paid { background:#dcfce7; color:#166534; }"
                        + "  .status-badge.cancelled,.status-badge.rejected { background:#fee2e2; color:#991b1b; }"
                        + "  .section-title { font-size:13px; font-weight:700; color:#4f46e5; text-transform:uppercase;"
                        + "                   letter-spacing:.6px; margin:28px 0 12px; border-left:3px solid #4f46e5;"
                        + "                   padding-left:10px; }"
                        + "  .content-box  { border:1px solid #e2e8f0; border-radius:10px; padding:20px 24px;"
                        + "                  font-size:14px; color:#334155; background:#fafafa; }"
                        + "  .sig-row      { display:flex; gap:40px; margin-top:40px; }"
                        + "  .sig-block    { flex:1; }"
                        + "  .sig-label    { font-size:11px; font-weight:700; color:#64748b; text-transform:uppercase;"
                        + "                  letter-spacing:.6px; margin-bottom:10px; }"
                        + "  .sig-image    { max-height:80px; max-width:220px; border:1px solid #e2e8f0;"
                        + "                  border-radius:8px; padding:4px; background:white; }"
                        + "  .sig-empty    { height:70px; border:2px dashed #cbd5e1; border-radius:8px;"
                        + "                  display:flex; align-items:center; justify-content:center;"
                        + "                  color:#94a3b8; font-size:13px; font-style:italic; }"
                        + "  .footer       { margin-top:48px; padding-top:16px; border-top:1px solid #e2e8f0;"
                        + "                  font-size:11px; color:#94a3b8; text-align:center; }"
                        + "  hr { border:none; border-top:1px solid #e2e8f0; margin:28px 0; }"
                        + "</style></head><body>"
                        + "<div class='brand-header'>"
                        + "  <div><div class='brand-logo'>UNIDAR</div><div class='brand-sub'>Student Housing Platform</div></div>"
                        + "  <div style='margin-left:auto; text-align:right'>"
                        + "    <div class='doc-title'>Rental Agreement</div>"
                        + "    <div class='doc-id'>#" + contractId + "</div>"
                        + "  </div>"
                        + "</div>"
                        + "<div class='meta-grid'>"
                        + "  <div class='meta-item'><label>Student</label><span>" + escHtml(studentName) + "</span></div>"
                        + "  <div class='meta-item'><label>Owner</label><span>" + escHtml(ownerName) + "</span></div>"
                        + "  <div class='meta-item'><label>Property</label><span>" + escHtml(listingTitle) + "</span></div>"
                        + "  <div class='meta-item'><label>Monthly Rent</label><span>" + String.format("%.0f TND", rent) + "</span></div>"
                        + "  <div class='meta-item'><label>Start Date</label><span>" + escHtml(startDate) + "</span></div>"
                        + "  <div class='meta-item'><label>End Date</label><span>" + escHtml(endDate) + "</span></div>"
                        + "  <div class='meta-item'><label>Status</label><span><span class='status-badge " + status.toLowerCase() + "'>" + escHtml(ContractStatusUi.formatLabel(status)) + "</span></span></div>"
                        + "</div>"
                        + "<div class='section-title'>Contract Terms</div>"
                        + "<div class='content-box'>" + rawBody + "</div>"
                        + "<div class='section-title'>Signatures</div>"
                        + "<div class='sig-row'>" + sigStudent + sigOwner + "</div>"
                        + "<div class='footer'>Generated by Unidar Desktop &bull; UNIDAR © 2026</div>"
                        + "</body></html>";
                }

                javafx.application.Platform.runLater(() -> {
                    root.getChildren().remove(lblLoading);
                    webView.getEngine().loadContent(html);
                });
            } catch (Exception ex) {
                ex.printStackTrace();
                javafx.application.Platform.runLater(() ->
                        lblLoading.setText("Error: " + ex.getMessage()));
            }
        }).start();

        Scene scene = new Scene(root);
        try {
            scene.getStylesheets().add(
                    ContractViewer.class.getResource("/css/unidar-pages.css").toExternalForm());
        } catch (Exception ignored) { /* css optional */ }
        dialog.setScene(scene);
        dialog.show();
    }

    // ── Helpers ─────────────────────────────────────────────────────────────

    /** Build a signature block — renders real image if URL/base64 present, placeholder otherwise. */
    private static String buildSigHtml(String sig, String label, String name) {
        String imageSection;
        if (sig != null && !sig.isBlank()) {
            String src = sig;
            if (!sig.startsWith("http") && !sig.startsWith("data:")) {
                // If it's just a path, resolve it
                src = ApiClient.resolveImageUrl(sig);
            }
            imageSection = "<img class='sig-image' src='" + src + "' alt='signature'/>";
        } else {
            imageSection = "<div class='sig-empty'>Awaiting signature</div>";
        }
        return "<div class='sig-block'>"
             + "<div class='sig-label'>" + escHtml(label) + "</div>"
             + imageSection
             + "<div style='font-size:12px;color:#64748b;margin-top:6px;'>" + escHtml(name) + "</div>"
             + "</div>";
    }

    /** Replace signature placeholder tokens with actual image tags in pre-built HTML. */
    private static String injectSignatures(String html, String studentSig, String ownerSig) {
        html = replaceSignaturePlaceholders(html, studentSig, new String[]{
            "{{STUDENT_SIGNATURE}}",
            "[VOTRE SIGNATURE CI-DESSOUS]",
            "[Student Signature Here]",
            "[Signature étudiant ici]"
        });
        html = replaceSignaturePlaceholders(html, ownerSig, new String[]{
            "{{OWNER_SIGNATURE}}",
            "[Signé via Plateforme]",
            "[Owner Signature]",
            "[Electronically Verified]"
        });
        return html;
    }

    private static String replaceSignaturePlaceholders(String html, String sig, String[] tokens) {
        if (sig == null || sig.isBlank()) {
            // Remove placeholder tokens and show a styled "Awaiting" message
            for (String token : tokens) {
                html = html.replace(token,
                    "<span style='font-style:italic;color:#94a3b8;font-size:12px;'>Awaiting signature</span>");
            }
            return html;
        }
        
        String src = sig;
        if (!sig.startsWith("http") && !sig.startsWith("data:")) {
            src = ApiClient.resolveImageUrl(sig);
        }
        
        String imgTag  = "<img style='max-height:80px;max-width:220px;"
                       + "border:1px solid #e2e8f0;border-radius:6px;display:block;margin-top:4px;' "
                       + "src='" + src + "' alt='signature'/>";
        for (String token : tokens) {
            html = html.replace(token, imgTag);
        }
        return html;
    }

    private static String escHtml(String s) {
        if (s == null || s.isEmpty()) return "";
        return s.replace("&", "&amp;")
                .replace("<", "&lt;")
                .replace(">", "&gt;")
                .replace("\"", "&quot;");
    }
}
