package tn.unidar.desktop.controllers;

import javafx.application.Platform;
import javafx.fxml.FXML;
import javafx.fxml.Initializable;
import javafx.geometry.Pos;
import javafx.scene.control.*;
import javafx.scene.layout.*;
import org.json.JSONArray;
import org.json.JSONObject;
import tn.unidar.desktop.services.ApiClient;
import tn.unidar.desktop.services.AuthService;
import tn.unidar.desktop.services.NavigationService;
import tn.unidar.desktop.utils.I18n;

import java.net.URL;
import java.util.ArrayList;
import java.util.List;
import java.util.ResourceBundle;

public class AdminReportsController implements Initializable {

    @FXML private VBox             loadingPane, emptyPane, reportsContainer;
    @FXML private Label            lblCount, lblTitle;
    @FXML private ComboBox<String> comboStatus;
    @FXML private Button           btnBack, btnFilter, btnRefresh;

    private List<JSONObject> allReports = new ArrayList<>();

    @Override
    public void initialize(URL location, ResourceBundle resources) {
        if (!AuthService.getInstance().isLoggedIn()) {
            NavigationService.getInstance().navigateTo("Login.fxml");
            return;
        }
        comboStatus.getItems().addAll("open", "resolved", "all");
        comboStatus.setValue("open");
        loadReports("open");
    }

    private void loadReports(String status) {
        show(loadingPane, true);
        show(emptyPane, false);
        reportsContainer.getChildren().clear();

        String endpoint = "/reports.php"
                + (status != null && !status.equals("all") ? "?status=" + status : "");

        new Thread(() -> {
            try {
                JSONObject resp = ApiClient.getInstance().get(endpoint);
                JSONArray arr = resp.optJSONArray("reports");
                allReports.clear();
                if (arr != null) {
                    for (int i = 0; i < arr.length(); i++) allReports.add(arr.getJSONObject(i));
                }
                Platform.runLater(() -> renderReports(allReports));
            } catch (Exception e) {
                e.printStackTrace();
                Platform.runLater(() -> {
                    show(loadingPane, false);
                    lblCount.setText(I18n.t("common.error"));
                });
            }
        }).start();
    }

    @FXML
    private void handleFilter() {
        loadReports(comboStatus.getValue());
    }

    @FXML
    private void refresh() {
        loadReports(comboStatus.getValue());
    }

    private void renderReports(List<JSONObject> reports) {
        show(loadingPane, false);
        reportsContainer.getChildren().clear();

        if (reports.isEmpty()) {
            show(emptyPane, true);
            lblCount.setText("0 " + I18n.t("admin.stats.reports"));
            return;
        }
        show(emptyPane, false);
        lblCount.setText(reports.size() + " " + I18n.t("admin.stats.reports"));

        for (JSONObject r : reports) {
            reportsContainer.getChildren().add(buildReportCard(r));
        }
    }

    private VBox buildReportCard(JSONObject r) {
        // Handle nested structure if present
        JSONObject report = r.has("report") ? r.getJSONObject("report") : r;

        int    id           = report.optInt("id", 0);
        String type         = report.optString("type", "listing");
        String reason       = report.optString("reason", "—");
        String status       = report.optString("status", "open");
        String reporterName = report.optString("reporter_name",
                                "User #" + report.optInt("reporter_id"));
        String targetName   = report.optString("target_name", "");
        String createdAt    = report.optString("created_at", "")
                .replace("T", " ").replaceAll("\\..*", "");

        VBox card = new VBox(12);
        card.setStyle("-fx-background-color: white; -fx-background-radius: 12;" +
                      " -fx-border-color: #e2e8f0; -fx-border-radius: 12; -fx-border-width: 1;" +
                      " -fx-padding: 20;");

        // ── Top row ──
        HBox top = new HBox(12);
        top.setAlignment(Pos.CENTER_LEFT);

        Label icon = new Label("🚩");
        icon.setStyle("-fx-font-size: 26px;");

        VBox info = new VBox(4);
        HBox.setHgrow(info, Priority.ALWAYS);

        Label lReason = new Label(reason);
        lReason.setStyle("-fx-font-weight: bold; -fx-font-size: 14px; -fx-text-fill: #0f172a;");
        lReason.setWrapText(true);

        String subtitle = I18n.t("admin.by") + " " + reporterName;
        if (!targetName.isEmpty()) subtitle += "  →  " + targetName;
        Label lMeta = new Label(subtitle + "  ·  " + createdAt.substring(0, Math.min(10, createdAt.length())));
        lMeta.setStyle("-fx-text-fill: #64748b; -fx-font-size: 12px;");
        info.getChildren().addAll(lReason, lMeta);

        String badgeColor = "resolved".equals(status) ? "#16a34a" : "#d97706";
        String statusLabel = "resolved".equals(status)
                ? I18n.t("admin.status.resolved")
                : I18n.t("admin.status.open");
        Label lStatus = new Label(statusLabel);
        lStatus.setStyle("-fx-background-color: " + badgeColor + "22; -fx-text-fill: " + badgeColor + ";" +
                         " -fx-font-weight: bold; -fx-font-size: 12px;" +
                         " -fx-background-radius: 8; -fx-padding: 4 10;");

        top.getChildren().addAll(icon, info, lStatus);

        // ── Detail row ──
        HBox details = new HBox(32);
        details.setAlignment(Pos.CENTER_LEFT);
        details.getChildren().addAll(
                detailBox(I18n.t("admin.report.type"), capitalize(type)),
                detailBox(I18n.t("admin.report.reporter"), reporterName),
                detailBox(I18n.t("admin.users.joined"), createdAt.substring(0, Math.min(10, createdAt.length())))
        );

        card.getChildren().addAll(top, details);

        // ── Action buttons (only for open reports) ──
        if ("open".equals(status)) {
            HBox actions = new HBox(10);
            actions.setAlignment(Pos.CENTER_RIGHT);

            Button btnDismiss = new Button("✓  " + I18n.t("admin.report.dismiss"));
            btnDismiss.setStyle("-fx-background-color: #f0fdf4; -fx-text-fill: #16a34a;" +
                                " -fx-background-radius: 8; -fx-font-weight: bold; -fx-cursor: hand;");
            btnDismiss.setOnAction(e -> resolveReport(id, "resolved", null, btnDismiss));

            Button btnBan = new Button("🚫  " + I18n.t("admin.report.ban_user"));
            btnBan.setStyle("-fx-background-color: #fef2f2; -fx-text-fill: #dc2626;" +
                            " -fx-background-radius: 8; -fx-font-weight: bold; -fx-cursor: hand;");

            int reportedUserId = report.optInt("reported_user_id",
                    report.optInt("target_user_id", 0));
            if (reportedUserId > 0) {
                btnBan.setOnAction(e -> banUserAndResolve(reportedUserId, id, btnBan));
            } else {
                btnBan.setDisable(true);
            }

            actions.getChildren().addAll(btnDismiss, btnBan);
            card.getChildren().add(actions);
        }

        return card;
    }

    private void resolveReport(int reportId, String resolution, Button banBtn, Button dismissBtn) {
        if (dismissBtn != null) { dismissBtn.setDisable(true); dismissBtn.setText(I18n.t("admin.processing")); }
        new Thread(() -> {
            try {
                JSONObject body = new JSONObject()
                        .put("report_id", reportId)
                        .put("action", resolution);
                JSONObject resp = ApiClient.getInstance().put("/reports.php?id=" + reportId, body);
                Platform.runLater(() -> {
                    if (resp.optBoolean("success", false)) {
                        loadReports(comboStatus.getValue());
                    } else {
                        String err = resp.optString("error", I18n.t("common.error"));
                        new Alert(Alert.AlertType.ERROR, err, ButtonType.OK).showAndWait();
                        if (dismissBtn != null) { dismissBtn.setDisable(false); dismissBtn.setText("✓  " + I18n.t("admin.report.dismiss")); }
                    }
                });
            } catch (Exception e) {
                e.printStackTrace();
                Platform.runLater(() -> {
                    if (dismissBtn != null) { dismissBtn.setDisable(false); dismissBtn.setText("✓  " + I18n.t("admin.report.dismiss")); }
                });
            }
        }).start();
    }

    private void banUserAndResolve(int userId, int reportId, Button btn) {
        Alert confirm = new Alert(Alert.AlertType.CONFIRMATION,
                I18n.t("admin.report.ban_confirm"),
                ButtonType.YES, ButtonType.NO);
        confirm.setHeaderText(null);
        confirm.showAndWait().ifPresent(res -> {
            if (res != ButtonType.YES) return;
            btn.setDisable(true);
            btn.setText(I18n.t("admin.processing"));
            new Thread(() -> {
                try {
                    // Ban the user
                    JSONObject banBody = new JSONObject()
                            .put("status", "banned")
                            .put("notes", "Banned via report #" + reportId);
                    ApiClient.getInstance().put("/admin.php?user_id=" + userId, banBody);

                    // Resolve the report
                    JSONObject resolveBody = new JSONObject()
                            .put("report_id", reportId)
                            .put("action", "resolved");
                    ApiClient.getInstance().put("/reports.php?id=" + reportId, resolveBody);

                    Platform.runLater(() -> loadReports(comboStatus.getValue()));
                } catch (Exception e) {
                    e.printStackTrace();
                    Platform.runLater(() -> {
                        btn.setDisable(false);
                        btn.setText("🚫  " + I18n.t("admin.report.ban_user"));
                        new Alert(Alert.AlertType.ERROR, I18n.t("common.error")).show();
                    });
                }
            }).start();
        });
    }

    private VBox detailBox(String label, String value) {
        VBox box = new VBox(3);
        Label lbl = new Label(label);
        lbl.setStyle("-fx-text-fill: #94a3b8; -fx-font-size: 11px; -fx-font-weight: bold;");
        Label val = new Label(value);
        val.setStyle("-fx-text-fill: #334155; -fx-font-size: 13px;");
        box.getChildren().addAll(lbl, val);
        return box;
    }

    @FXML private void goBack() { NavigationService.getInstance().navigateTo("AdminDashboard.fxml"); }

    private void show(javafx.scene.Node n, boolean v) { n.setVisible(v); n.setManaged(v); }

    private String capitalize(String s) {
        if (s == null || s.isEmpty()) return s;
        return Character.toUpperCase(s.charAt(0)) + s.substring(1).toLowerCase();
    }
}
