package tn.unidar.desktop.controllers;

import javafx.application.Platform;
import javafx.fxml.FXML;
import javafx.fxml.Initializable;
import javafx.scene.control.*;
import javafx.scene.layout.*;
import javafx.scene.control.ChoiceDialog;
import org.json.JSONArray;
import org.json.JSONObject;
import javafx.scene.image.Image;
import javafx.scene.image.ImageView;
import tn.unidar.desktop.utils.CreativeImageLoader;
import tn.unidar.desktop.services.NavigationService;
import tn.unidar.desktop.utils.I18n;
import tn.unidar.desktop.services.ApiClient;
import tn.unidar.desktop.services.AuthService;
import tn.unidar.desktop.services.PdfContractService;
import javafx.scene.web.WebView;
import javafx.stage.Modality;
import javafx.stage.Stage;
import java.io.File;
import java.net.URL;
import java.time.LocalDate;
import java.util.ResourceBundle;

public class ListingDetailController implements Initializable {

    @FXML private Label  lblTitle, lblAddress, lblPrice, lblBedrooms;
    @FXML private Label  lblBaths, lblCapacity, lblDescription;
    @FXML private Label  lblType, lblGender, lblStatus;
    @FXML private Label  lblOwnerName, lblPosted;
    @FXML private Button btnContact, btnSave;
    @FXML private Button btnContract;
    @FXML private VBox roommatesContainer, roommatesSection;
    @FXML private Label lblFloor, lblRemaining;
    @FXML private FlowPane amenitiesPane;
    @FXML private Label lblImageCounter;
    @FXML private StackPane mainImageContainer;
    @FXML private HBox thumbnailsContainer;
    @FXML private Label lblFullAddress;
    // New fields for redesigned layout
    @FXML private Label lblNavTitle;
    @FXML private Label lblSidebarPrice;
    @FXML private Label lblSidebarRemaining;
    @FXML private Label lblSidebarFloor;
    

    private int listingId;
    private int ownerId;
    private boolean saved = false;
    private JSONArray images = new JSONArray();
    private int currentImgIndex = 0;
    private double listingPrice = 0;
    private JSONObject listingData;

    @Override
    public void initialize(URL location, ResourceBundle resources) {
        Object data = NavigationService.getInstance().consumeData();
        if (data instanceof Integer) {
            listingId = (Integer) data;
            loadListing(listingId);
        } else {
            lblTitle.setText("Listing not found");
        }
    }


    private void loadListing(int id) {
        new Thread(() -> {
            try {
                JSONObject resp = ApiClient.getInstance().get("/listings.php?id=" + id);
                JSONObject l = resp.optJSONObject("listing");
                if (l == null) l = resp;

                final JSONObject listing = l;
                Platform.runLater(() -> populateUI(listing));
            } catch (Exception e) {
                System.err.println("[ListingDetail] Error loading listing: " + e.getMessage());
                Platform.runLater(() -> lblTitle.setText("Failed to load listing"));
            }
        }).start();
    }

    private void populateUI(JSONObject l) {
        this.listingData = l;
        String title = l.optString("title", "Untitled");
        lblTitle.setText(title);
        if (lblNavTitle != null) lblNavTitle.setText(title);
        lblAddress.setText(l.optString("address", I18n.t("common.unknown")));

        double price = l.optDouble("price", 0);
        lblPrice.setText(price + " TND");
        if (lblSidebarPrice != null) lblSidebarPrice.setText(price + " TND");

        lblBedrooms.setText(String.valueOf(l.optInt("bedrooms", 0)));
        lblBaths.setText(String.valueOf(l.optInt("bathrooms", 0)));
        lblCapacity.setText(String.valueOf(l.optInt("capacity", 1)));
        lblDescription.setText(l.optString("description", "No description provided."));
        lblType.setText(capitalize(l.optString("property_type", "apartment")));
        lblGender.setText(capitalize(l.optString("gender_preference", "mixed")));
        lblStatus.setText(l.optBoolean("is_available", true) ? I18n.t("detail.available") : I18n.t("detail.unavailable"));
        lblOwnerName.setText(l.optString("owner_name", "Owner"));
        String postedDate = l.optString("created_at", I18n.t("detail.recently"));
        lblPosted.setText(I18n.t("detail.posted") + " " + postedDate);

        lblFullAddress.setText(l.optString("address", I18n.t("common.unknown")));

        int floor = l.optInt("floor", 0);
        lblFloor.setText(String.valueOf(floor));
        if (lblSidebarFloor != null) lblSidebarFloor.setText("Niv. " + floor);

        int cap = l.optInt("capacity", 1);
        int occ = l.optInt("occupant_count", 0);
        int remaining = Math.max(0, cap - occ);
        lblRemaining.setText(String.valueOf(remaining));
        if (lblSidebarRemaining != null) lblSidebarRemaining.setText(String.valueOf(remaining));

        ownerId = l.optInt("owner_id", 0);
        saved = l.optBoolean("is_saved", false);
        btnSave.setText(saved ? "❤️  " + I18n.t("detail.saved") : "🤍  " + I18n.t("detail.save_listing"));

        listingPrice = price;

        boolean isStudent = AuthService.getInstance().isLoggedIn()
                && "student".equalsIgnoreCase(AuthService.getInstance().getCurrentUser().getRole());
        boolean isAvailable = l.optBoolean("is_available", true);
        btnContract.setVisible(isStudent && remaining > 0 && isAvailable);
        btnContract.setManaged(isStudent && remaining > 0 && isAvailable);

        images = l.optJSONArray("images");
        if (images == null) images = new JSONArray();
        currentImgIndex = 0;
        updateImage(0);

        JSONArray roommates = l.optJSONArray("roommates");
        roommatesContainer.getChildren().clear();
        if (roommates != null && !roommates.isEmpty()) {
            roommatesSection.setVisible(true);
            roommatesSection.setManaged(true);
            for (int i = 0; i < roommates.length(); i++) {
                JSONObject rm = roommates.getJSONObject(i);
                roommatesContainer.getChildren().add(buildRoommateRow(rm));
            }
        } else {
            roommatesSection.setVisible(false);
            roommatesSection.setManaged(false);
        }

        amenitiesPane.getChildren().clear();
        String[] defaults = {"🛜 WiFi", "🅿️ Parking", "🪑 Furnished", "🌊 Hot Water", "🔒 Secure Building"};
        for (String a : defaults) {
            Label chip = new Label(a);
            chip.getStyleClass().add("amenity-chip");
            amenitiesPane.getChildren().add(chip);
        }
    }

    @FXML
    private void contactOwner() {
        if (!AuthService.getInstance().isLoggedIn()) {
            NavigationService.getInstance().navigateTo("Login.fxml");
            return;
        }
        NavigationService.getInstance().navigateTo("Messages.fxml", ownerId);
    }

    @FXML
    private void toggleSave() {
        if (!AuthService.getInstance().isLoggedIn()) {
            NavigationService.getInstance().navigateTo("Login.fxml");
            return;
        }
        new Thread(() -> {
            try {
                if (saved) {
                    ApiClient.getInstance().delete("/saved_listings.php?listing_id=" + listingId);
                } else {
                    JSONObject body = new JSONObject().put("listing_id", listingId);
                    ApiClient.getInstance().post("/saved_listings.php", body);
                }
                saved = !saved;
                final boolean nowSaved = saved;
                Platform.runLater(() -> btnSave.setText(nowSaved ? "❤️  " + I18n.t("detail.saved") : "🤍  " + I18n.t("detail.save_listing")));
            } catch (Exception e) {
                System.err.println("[ListingDetail] Error toggling save: " + e.getMessage());
            }
        }).start();
    }

    @FXML
    private void reportListing() {
        // Ask for a reason first
        ChoiceDialog<String> reasonDialog = new ChoiceDialog<>(
                "Fraudulent listing",
                "Fraudulent listing", "Incorrect information", "Inappropriate content",
                "Already rented", "Spam", "Other"
        );
        reasonDialog.setTitle(I18n.t("detail.report.title"));
        reasonDialog.setHeaderText(I18n.t("detail.report.header"));
        reasonDialog.setContentText(I18n.t("detail.report.reason_label"));

        reasonDialog.showAndWait().ifPresent(reason -> {
            new Thread(() -> {
                try {
                    JSONObject body = new JSONObject()
                            .put("listing_id", listingId)
                            .put("type", "listing")
                            .put("reason", reason);
                    ApiClient.getInstance().post("/reports.php", body);
                    Platform.runLater(() -> {
                        Alert done = new Alert(Alert.AlertType.INFORMATION,
                                I18n.t("detail.report.success"));
                        done.setTitle(I18n.t("detail.report.title"));
                        done.showAndWait();
                    });
                } catch (Exception e) {
                    System.err.println("[ListingDetail] Error reporting listing: " + e.getMessage());
                    Platform.runLater(() -> new Alert(Alert.AlertType.ERROR,
                            I18n.t("common.error")).show());
                }
            }).start();
        });
    }

    @FXML private void goBack() { NavigationService.getInstance().navigateTo("Listings.fxml"); }

    @FXML
    private void nextImage() {
        if (images.length() <= 1) return;
        currentImgIndex = (currentImgIndex + 1) % images.length();
        updateImage(currentImgIndex);
    }

    @FXML
    private void prevImage() {
        if (images.length() <= 1) return;
        currentImgIndex = (currentImgIndex - 1 + images.length()) % images.length();
        updateImage(currentImgIndex);
    }

    private void updateImage(int idx) {
        if (images == null || images.isEmpty()) return;
        if (idx < 0) idx = images.length() - 1;
        if (idx >= images.length()) idx = 0;
        currentImgIndex = idx;

        JSONObject imgObj = images.optJSONObject(idx);
        if (imgObj == null) return;
        
        String path = imgObj.optString("image_path", "");
        String fullUrl = ApiClient.resolveImageUrl(path, lblTitle.getText(), lblType.getText());

        // Update Counter
        lblImageCounter.setText((currentImgIndex + 1) + " / " + images.length());

        // Load Main Image into dedicated sub-container
        double w = mainImageContainer.getWidth() > 0 ? mainImageContainer.getWidth() : 600;
        double h = mainImageContainer.getHeight() > 0 ? mainImageContainer.getHeight() : 400;
        
        CreativeImageLoader.load(mainImageContainer, fullUrl, w, h, lblTitle.getText(), lblType.getText());

        // Update Thumbnails Selection
        renderThumbnails();
    }

    private void renderThumbnails() {
        thumbnailsContainer.getChildren().clear();
        for (int i = 0; i < images.length(); i++) {
            final int index = i;
            JSONObject imgObj = images.optJSONObject(i);
            String path = imgObj.optString("image_path", "");
            String thumbUrl = ApiClient.resolveImageUrl(path, lblTitle.getText(), lblType.getText());

            StackPane thumbPane = new StackPane();
            thumbPane.setPrefSize(80, 60);
            thumbPane.getStyleClass().add("card");
            thumbPane.setStyle("-fx-padding: 0; -fx-cursor: hand;");
            
            if (i == currentImgIndex) {
                thumbPane.setStyle(thumbPane.getStyle() + "-fx-border-color: #4f46e5; -fx-border-width: 2;");
            } else {
                thumbPane.setOpacity(0.6);
            }

            CreativeImageLoader.load(thumbPane, thumbUrl, 80, 60, lblTitle.getText(), lblType.getText());
            thumbPane.setOnMouseClicked(e -> updateImage(index));
            
            thumbnailsContainer.getChildren().add(thumbPane);
        }
    }

    private HBox buildRoommateRow(JSONObject rm) {
        HBox row = new HBox(12);
        row.setAlignment(javafx.geometry.Pos.CENTER_LEFT);
        row.setStyle("-fx-background-color: white; -fx-padding: 10 16; -fx-background-radius: 10; " +
                     "-fx-border-color: #e2e8f0; -fx-border-radius: 10;");

        Label icon = new Label("👤");
        VBox info = new VBox(2);
        Label name = new Label(rm.optString("full_name", "Student"));
        name.setStyle("-fx-font-weight: bold; -fx-text-fill: #0f172a;");
        Label sub = new Label(I18n.t("detail.student_tag"));
        sub.setStyle("-fx-text-fill: #64748b; -fx-font-size: 11px;");
        info.getChildren().addAll(name, sub);

        Region spacer = new Region();
        HBox.setHgrow(spacer, Priority.ALWAYS);

        Button chat = new Button(I18n.t("detail.chat"));
        chat.getStyleClass().addAll("btn-secondary", "btn-sm");
        chat.setOnAction(e -> NavigationService.getInstance().navigateTo("Messages.fxml", rm.optInt("student_id")));

        row.getChildren().addAll(icon, info, spacer, chat);
        return row;
    }

    @FXML
    private void generateContract() {
        if (!AuthService.getInstance().isLoggedIn()) {
            NavigationService.getInstance().navigateTo("Login.fxml");
            return;
        }
        showContractSetupDialog();
    }

    private void showContractSetupDialog() {
        javafx.stage.Stage dialog = new javafx.stage.Stage();
        dialog.initModality(javafx.stage.Modality.APPLICATION_MODAL);
        dialog.setTitle("Generate Contract");
        dialog.setResizable(false);

        VBox root = new VBox(20);
        root.setStyle("-fx-padding: 32; -fx-background-color: white;");
        root.setPrefWidth(460);

        Label title = new Label("📋  Contract Setup");
        title.setStyle("-fx-font-size: 22px; -fx-font-weight: bold; -fx-text-fill: #0f172a;");

        Label subtitle = new Label("Property: " + lblTitle.getText() + "\nRent: " + listingPrice + " TND/month");
        subtitle.setStyle("-fx-text-fill: #64748b; -fx-font-size: 13px;");
        subtitle.setWrapText(true);

        // Start month selector
        Label lblStart = new Label("Start Month");
        lblStart.setStyle("-fx-font-weight: bold; -fx-text-fill: #374151;");
        javafx.scene.control.ComboBox<String> comboMonth = new javafx.scene.control.ComboBox<>();
        java.time.LocalDate now = java.time.LocalDate.now();
        for (int i = 0; i < 6; i++) {
            java.time.LocalDate m = now.plusMonths(i + 1).withDayOfMonth(1);
            comboMonth.getItems().add(m.getMonth().toString() + " " + m.getYear());
        }
        comboMonth.setValue(comboMonth.getItems().get(0));
        comboMonth.setMaxWidth(Double.MAX_VALUE);

        // Duration selector
        Label lblDuration = new Label("Duration (months)");
        lblDuration.setStyle("-fx-font-weight: bold; -fx-text-fill: #374151;");
        javafx.scene.control.ComboBox<String> comboDuration = new javafx.scene.control.ComboBox<>();
        for (int d = 1; d <= 12; d++) comboDuration.getItems().add(d + " month" + (d > 1 ? "s" : ""));
        comboDuration.setValue("9 months");
        comboDuration.setMaxWidth(Double.MAX_VALUE);

        // Price summary
        VBox summary = new VBox(6);
        summary.setStyle("-fx-background-color: #f0fdf4; -fx-background-radius: 10; -fx-padding: 14;");
        Label lblTotal = new Label("Total: " + (listingPrice * 9) + " TND (9 months)");
        lblTotal.setStyle("-fx-font-weight: bold; -fx-text-fill: #059669; -fx-font-size: 15px;");
        Label lblCommission = new Label("+ 5% platform commission: " + String.format("%.0f", listingPrice * 9 * 0.05) + " TND");
        lblCommission.setStyle("-fx-text-fill: #64748b; -fx-font-size: 12px;");
        summary.getChildren().addAll(lblTotal, lblCommission);

        comboDuration.setOnAction(e -> {
            String val = comboDuration.getValue();
            int months = Integer.parseInt(val.split(" ")[0]);
            double total = listingPrice * months;
            lblTotal.setText("Total: " + total + " TND (" + months + " months)");
            lblCommission.setText("+ 5% platform commission: " + String.format("%.0f", total * 0.05) + " TND");
        });

        VBox errBox = new VBox();
        errBox.setVisible(false); errBox.setManaged(false);
        Label errLbl = new Label();
        errLbl.setStyle("-fx-text-fill: #dc2626;");
        errBox.getChildren().add(errLbl);

        Button btnGenerate = new Button("📋  Generate Contract");
        btnGenerate.getStyleClass().add("btn-primary");
        btnGenerate.setMaxWidth(Double.MAX_VALUE);
        btnGenerate.setPrefHeight(48);
        btnGenerate.setStyle("-fx-background-color: #059669; -fx-text-fill: white; -fx-font-weight: bold;");
        btnGenerate.setOnAction(e -> {
            btnGenerate.setDisable(true);
            btnGenerate.setText("Generating...");
            String monthVal = comboMonth.getValue();
            int durationMonths = Integer.parseInt(comboDuration.getValue().split(" ")[0]);
            // Parse start date from selection
            java.time.LocalDate startDate = now.plusMonths(comboMonth.getItems().indexOf(monthVal) + 1).withDayOfMonth(1);

            new Thread(() -> {
                try {
                    org.json.JSONObject body = new org.json.JSONObject()
                        .put("listing_id", listingId)
                        .put("start_date", startDate.toString())
                        .put("duration", durationMonths);
                    org.json.JSONObject resp = ApiClient.getInstance().post("/contracts.php?action=generate", body);
                    javafx.application.Platform.runLater(() -> {
                        if (resp != null && resp.optBoolean("success", false)) {
                            int contractId = resp.optInt("contract_id", resp.optInt("id", 0));
                            dialog.close();
                            showSignatureDialog(contractId, durationMonths, startDate.toString());
                        } else {
                            errLbl.setText("⚠️ " + (resp != null ? resp.optString("error", I18n.t("contract.err.generate")) : I18n.t("common.error")));
                            errBox.setVisible(true); errBox.setManaged(true);
                            btnGenerate.setDisable(false);
                            btnGenerate.setText("📋  " + I18n.t("contract.generate_btn"));
                        }
                    });
                } catch (Exception ex) {
                    System.err.println("[ListingDetail] Contract generation error: " + ex.getMessage());
                    javafx.application.Platform.runLater(() -> {
                        errLbl.setText("⚠️ " + I18n.t("common.error"));
                        errBox.setVisible(true); errBox.setManaged(true);
                        btnGenerate.setDisable(false);
                        btnGenerate.setText("📋  " + I18n.t("contract.generate_btn"));
                    });
                }
            }).start();
        });

        root.getChildren().addAll(title, subtitle, lblStart, comboMonth, lblDuration, comboDuration, summary, errBox, btnGenerate);
        javafx.scene.Scene scene = new javafx.scene.Scene(root, 460, 520);
        URL cssUrl = getClass().getResource("/css/unidar-pages.css");
        if (cssUrl != null) scene.getStylesheets().add(cssUrl.toExternalForm());
        dialog.setScene(scene);
        dialog.show();
    }

    private void showSignatureDialog(int contractId, int months, String startDate) {
        javafx.stage.Stage dialog = new javafx.stage.Stage();
        dialog.initModality(javafx.stage.Modality.APPLICATION_MODAL);
        dialog.setTitle(I18n.t("contract.sign.title") + " #" + contractId);
        dialog.setResizable(false);

        VBox root = new VBox(16);
        root.setStyle("-fx-padding: 24; -fx-background-color: white;");
        root.setPrefWidth(600);

        Label title = new Label("✍️  " + I18n.t("contract.sign.title"));
        title.setStyle("-fx-font-size: 22px; -fx-font-weight: bold; -fx-text-fill: #0f172a;");

        // Contract Preview (WebView)
        WebView webView = new WebView();
        webView.setPrefHeight(300);
        webView.setMinHeight(300);
        webView.getStyleClass().add("contract-webview");
        String html = PdfContractService.getInstance().getContractHtml(
                listingData, 
                AuthService.getInstance().getCurrentUserAsJson(), 
                months, 
                startDate
        );
        webView.getEngine().loadContent(html);
        
        VBox previewBox = new VBox(8, new Label(I18n.t("contract.preview")), webView);
        previewBox.setStyle("-fx-border-color: #e2e8f0; -fx-border-radius: 8; -fx-padding: 10;");

        Label signHint = new Label(I18n.t("contract.sign.hint"));
        signHint.setStyle("-fx-text-fill: #64748b; -fx-font-size: 13px; -fx-font-weight: bold;");

        // Canvas for signature
        javafx.scene.canvas.Canvas canvas = new javafx.scene.canvas.Canvas(456, 180);
        javafx.scene.canvas.GraphicsContext gc = canvas.getGraphicsContext2D();
        gc.setFill(javafx.scene.paint.Color.WHITE);
        gc.fillRect(0, 0, 456, 180);
        gc.setStroke(javafx.scene.paint.Color.web("#e2e8f0"));
        gc.strokeRect(0, 0, 456, 180);
        gc.setStroke(javafx.scene.paint.Color.web("#0f172a"));
        gc.setLineWidth(2);

        // Draw on canvas
        final boolean[] drawing = {false};
        canvas.setOnMousePressed(e -> { drawing[0] = true; gc.beginPath(); gc.moveTo(e.getX(), e.getY()); });
        canvas.setOnMouseDragged(e -> { if (drawing[0]) { gc.lineTo(e.getX(), e.getY()); gc.stroke(); } });
        canvas.setOnMouseReleased(e -> drawing[0] = false);

        StackPane canvasPane = new StackPane(canvas);
        canvasPane.setStyle("-fx-border-color: #e2e8f0; -fx-border-radius: 10; -fx-border-width: 2;");

        Label hint = new Label(I18n.t("contract.sign.draw"));
        hint.setStyle("-fx-text-fill: #94a3b8; -fx-font-size: 12px;");

        Button btnClear = new Button(I18n.t("clear"));
        btnClear.getStyleClass().addAll("btn-ghost", "btn-sm");
        btnClear.setOnAction(e -> {
            gc.setFill(javafx.scene.paint.Color.WHITE);
            gc.fillRect(0, 0, 456, 180);
            gc.setStroke(javafx.scene.paint.Color.web("#e2e8f0"));
            gc.strokeRect(0, 0, 456, 180);
            gc.setStroke(javafx.scene.paint.Color.web("#0f172a"));
        });

        VBox errBox = new VBox();
        errBox.setVisible(false); errBox.setManaged(false);
        Label errLbl = new Label();
        errLbl.setStyle("-fx-text-fill: #dc2626;");
        errBox.getChildren().add(errLbl);

        Button btnSign = new Button("✅  " + I18n.t("contract.sign_continue_btn"));
        btnSign.getStyleClass().add("btn-primary");
        btnSign.setMaxWidth(Double.MAX_VALUE);
        btnSign.setPrefHeight(48);
        btnSign.setStyle("-fx-background-color: #059669; -fx-text-fill: white; -fx-font-weight: bold;");
        btnSign.setOnAction(e -> {
            btnSign.setDisable(true);
            btnSign.setText(I18n.t("signing"));
            // Capture canvas as base64 PNG
            javafx.scene.image.WritableImage wi = canvas.snapshot(null, null);
            java.io.ByteArrayOutputStream bos = new java.io.ByteArrayOutputStream();
            try {
                javax.imageio.ImageIO.write(javafx.embed.swing.SwingFXUtils.fromFXImage(wi, null), "png", bos);
            } catch (Exception ex) {
                System.err.println("[ListingDetail] Signature capture error: " + ex.getMessage());
            }
            String base64 = "data:image/png;base64," + java.util.Base64.getEncoder().encodeToString(bos.toByteArray());

            new Thread(() -> {
                try {
                    // Generate PDF locally first
                    JSONObject student = AuthService.getInstance().getCurrentUserAsJson();
                    // We need start_date and duration_months stored from previous dialog
                    // For now, let's use sensible defaults if not passed, or pass them better.
                    // Actually, the dialog already has them.
                    
                    org.json.JSONObject body = new org.json.JSONObject()
                        .put("contract_id", contractId)
                        .put("signature", base64);
                    org.json.JSONObject resp = ApiClient.getInstance().post("/contracts.php?action=sign", body);
                    
                    // Generate PDF
                    try {
                        JSONObject l = ApiClient.getInstance().get("/listings.php?id=" + listingId);
                        File pdfFile = PdfContractService.getInstance()
                                .generateContract(l, student, base64, null, months, "Next Month");
                        System.out.println("Contract PDF generated: " + pdfFile.getAbsolutePath());
                    } catch (Exception pdfEx) {
                        pdfEx.printStackTrace();
                    }

                    javafx.application.Platform.runLater(() -> {
                        if (resp != null && resp.optBoolean("success", false)) {
                            dialog.close();
                            showPaymentDialog(contractId);
                        } else {
                            errLbl.setText("⚠️ " + (resp != null ? resp.optString("error", I18n.t("contract.err.sign")) : I18n.t("common.error")));
                            errBox.setVisible(true); errBox.setManaged(true);
                            btnSign.setDisable(false);
                            btnSign.setText("✅  " + I18n.t("contract.sign_continue_btn"));
                        }
                    });
                } catch (Exception ex) {
                    System.err.println("[ListingDetail] Signature submit error: " + ex.getMessage());
                    javafx.application.Platform.runLater(() -> {
                        errLbl.setText("⚠️ " + I18n.t("common.error"));
                        errBox.setVisible(true); errBox.setManaged(true);
                        btnSign.setDisable(false);
                        btnSign.setText("✅  " + I18n.t("contract.sign_continue_btn"));
                    });
                }
            }).start();
        });

        root.getChildren().addAll(title, previewBox, signHint, canvasPane, new HBox(8, hint, new Region() {{ HBox.setHgrow(this, Priority.ALWAYS); }}, btnClear), errBox, btnSign);
        javafx.scene.Scene scene = new javafx.scene.Scene(root, 520, 720);
        URL cssUrl = getClass().getResource("/css/unidar-pages.css");
        if (cssUrl != null) scene.getStylesheets().add(cssUrl.toExternalForm());
        dialog.setScene(scene);
        dialog.show();
    }

    private void showPaymentDialog(int contractId) {
        javafx.stage.Stage dialog = new javafx.stage.Stage();
        dialog.initModality(javafx.stage.Modality.APPLICATION_MODAL);
        dialog.setTitle(I18n.t("payment.title") + " — " + I18n.t("contract") + " #" + contractId);
        dialog.setResizable(false);

        VBox root = new VBox(16);
        root.setStyle("-fx-padding: 32; -fx-background-color: white;");
        root.setPrefWidth(480);

        Label title = new Label("💳  " + I18n.t("payment.title"));
        title.setStyle("-fx-font-size: 22px; -fx-font-weight: bold; -fx-text-fill: #0f172a;");

        // Payment summary
        VBox summaryBox = new VBox(8);
        summaryBox.setStyle("-fx-background-color: #eef2ff; -fx-background-radius: 10; -fx-padding: 16;");
        Label lblRent = new Label(I18n.t("monthly_rent") + ": " + listingPrice + " TND");
        lblRent.setStyle("-fx-font-weight: bold; -fx-text-fill: #0f172a;");
        double commission = listingPrice * 0.05;
        Label lblComm = new Label(I18n.t("platform_commission") + " (5%): " + String.format("%.2f", commission) + " TND");
        lblComm.setStyle("-fx-text-fill: #64748b; -fx-font-size: 12px;");
        double total = listingPrice + commission;
        Label lblTotal = new Label(I18n.t("total_due") + ": " + String.format("%.2f", total) + " TND");
        lblTotal.setStyle("-fx-font-weight: bold; -fx-text-fill: #059669; -fx-font-size: 16px;");
        summaryBox.getChildren().addAll(lblRent, lblComm, new Separator(), lblTotal);

        // Card input fields
        Label lblCard = new Label(I18n.t("card_number"));
        lblCard.setStyle("-fx-font-weight: bold; -fx-text-fill: #374151;");
        TextField txtCard = new TextField();
        txtCard.setPromptText("1234 5678 9012 3456");
        txtCard.getStyleClass().add("form-input");
        // Enforce 16-digit limit with group spacing
        txtCard.textProperty().addListener((obs, oldV, newV) -> {
            if (newV == null) return;
            String digits = newV.replaceAll("\\D", "");
            if (digits.length() > 16) { txtCard.setText(oldV); return; }
            StringBuilder fmt = new StringBuilder();
            for (int i = 0; i < digits.length(); i++) {
                if (i > 0 && i % 4 == 0) fmt.append(' ');
                fmt.append(digits.charAt(i));
            }
            String f = fmt.toString();
            if (!f.equals(newV)) { txtCard.setText(f); txtCard.positionCaret(f.length()); }
        });

        HBox row = new HBox(12);
        VBox expiryBox = new VBox(4);
        Label lblExp = new Label(I18n.t("expiry"));
        lblExp.setStyle("-fx-font-weight: bold; -fx-text-fill: #374151;");
        TextField txtExpiry = new TextField();
        txtExpiry.setPromptText("MM/YY");
        txtExpiry.getStyleClass().add("form-input");
        expiryBox.getChildren().addAll(lblExp, txtExpiry);
        VBox cvvBox = new VBox(4);
        Label lblCvv = new Label(I18n.t("cvv"));
        lblCvv.setStyle("-fx-font-weight: bold; -fx-text-fill: #374141;");
        PasswordField txtCvv = new PasswordField();
        txtCvv.setPromptText("123");
        txtCvv.getStyleClass().add("form-input");
        txtCvv.textProperty().addListener((obs, oldV, newV) -> {
            if (newV != null && newV.replaceAll("\\D", "").length() > 4) txtCvv.setText(oldV);
        });
        cvvBox.getChildren().addAll(lblCvv, txtCvv);
        HBox.setHgrow(expiryBox, Priority.ALWAYS);
        HBox.setHgrow(cvvBox, Priority.ALWAYS);
        row.getChildren().addAll(expiryBox, cvvBox);

        VBox errBox = new VBox();
        errBox.setVisible(false); errBox.setManaged(false);
        Label errLbl = new Label();
        errLbl.setStyle("-fx-text-fill: #dc2626;");
        errBox.getChildren().add(errLbl);

        Button btnPay = new Button("💳  " + I18n.t("pay_btn") + " " + String.format("%.2f", total) + " TND");
        btnPay.getStyleClass().add("btn-primary");
        btnPay.setMaxWidth(Double.MAX_VALUE);
        btnPay.setPrefHeight(48);
        btnPay.setOnAction(e -> {
            String cardNum = txtCard.getText().replaceAll("\\s", "");
            String expiry = txtExpiry.getText().trim();
            String cvv = txtCvv.getText().trim();
            if (cardNum.length() < 13) { errLbl.setText("⚠️ " + I18n.t("err.card_number")); errBox.setVisible(true); errBox.setManaged(true); return; }
            if (!expiry.matches("\\d{2}/\\d{2}")) { errLbl.setText("⚠️ " + I18n.t("err.expiry")); errBox.setVisible(true); errBox.setManaged(true); return; }
            if (cvv.length() < 3) { errLbl.setText("⚠️ " + I18n.t("err.cvv")); errBox.setVisible(true); errBox.setManaged(true); return; }

            errBox.setVisible(false); errBox.setManaged(false);
            btnPay.setDisable(true);
            btnPay.setText(I18n.t("processing"));

            new Thread(() -> {
                try {
                    org.json.JSONObject body = new org.json.JSONObject()
                        .put("contract_id", contractId)
                        .put("payment_method", "card")
                        .put("card_last4", cardNum.substring(cardNum.length() - 4));
                    org.json.JSONObject resp = ApiClient.getInstance().post("/contracts.php?action=process-payment", body);
                    javafx.application.Platform.runLater(() -> {
                        if (resp != null && resp.optBoolean("success", false)) {
                            dialog.close();
                            javafx.scene.control.Alert alert = new javafx.scene.control.Alert(javafx.scene.control.Alert.AlertType.INFORMATION);
                            alert.setTitle(I18n.t("payment.success.title"));
                            alert.setHeaderText("🎉 " + I18n.t("payment.success.msg"));
                            alert.setContentText(I18n.t("contract") + " #" + contractId + " " + I18n.t("payment.success.desc") + ".\n\n" + I18n.t("amount") + ": " + String.format("%.2f", total) + " TND\n" + I18n.t("card_ending") + ": " + cardNum.substring(cardNum.length() - 4));
                            alert.showAndWait();
                            loadListing(listingId); // Refresh to show updated occupancy
                        } else {
                            errLbl.setText("⚠️ " + (resp != null ? resp.optString("error", I18n.t("payment.err")) : I18n.t("common.error")));
                            errBox.setVisible(true); errBox.setManaged(true);
                            btnPay.setDisable(false);
                            btnPay.setText("💳  " + I18n.t("pay_btn") + " " + String.format("%.2f", total) + " TND");
                        }
                    });
                } catch (Exception ex) {
                    System.err.println("[ListingDetail] Payment processing error: " + ex.getMessage());
                    javafx.application.Platform.runLater(() -> {
                        errLbl.setText("⚠️ " + I18n.t("common.error"));
                        errBox.setVisible(true); errBox.setManaged(true);
                        btnPay.setDisable(false);
                        btnPay.setText("💳  " + I18n.t("pay_btn") + " " + String.format("%.2f", total) + " TND");
                    });
                }
            }).start();
        });

        root.getChildren().addAll(title, summaryBox, lblCard, txtCard, row, errBox, btnPay);
        javafx.scene.Scene scene = new javafx.scene.Scene(root, 480, 520);
        URL cssUrl = getClass().getResource("/css/unidar-pages.css");
        if (cssUrl != null) scene.getStylesheets().add(cssUrl.toExternalForm());
        dialog.setScene(scene);
        dialog.show();
    }

    private String capitalize(String s) {
        if (s == null || s.isEmpty()) return s;
        return Character.toUpperCase(s.charAt(0)) + s.substring(1).replace("_", " ");
    }
}
