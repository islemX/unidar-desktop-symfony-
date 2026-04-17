package tn.unidar.desktop.controllers;

import javafx.application.Platform;
import javafx.fxml.FXML;
import javafx.fxml.Initializable;
import javafx.geometry.Pos;
import javafx.scene.Scene;
import javafx.scene.control.*;
import javafx.scene.layout.*;
import javafx.stage.FileChooser;
import javafx.stage.Modality;
import javafx.stage.Stage;
import org.json.JSONArray;
import org.json.JSONObject;
import tn.unidar.desktop.services.ApiClient;
import tn.unidar.desktop.services.AuthService;
import tn.unidar.desktop.services.NavigationService;
import tn.unidar.desktop.utils.CreativeImageLoader;
import tn.unidar.desktop.utils.I18n;
import tn.unidar.desktop.utils.ContractViewer;
import tn.unidar.desktop.utils.ContractStatusUi;
import tn.unidar.desktop.utils.SignaturePad;

import java.io.File;
import java.net.URL;
import java.util.ArrayList;
import java.util.List;
import java.util.ResourceBundle;
import javafx.scene.image.Image;
import javafx.scene.image.ImageView;

public class OwnerListingsController implements Initializable {

    @FXML private FlowPane listingsGrid;
    @FXML private VBox     contractsList;
    @FXML private VBox     listingsPane, contractsPane;
    @FXML private VBox     loadingPane, emptyListingsPane, emptyContractsPane;
    @FXML private VBox     alertError, alertSuccess;
    @FXML private Label    lblError, lblSuccess, lblOwnerTitle, lblOwnerSubtitle, lblLoading;
    @FXML private Label    lblEmptyListingsTitle, lblEmptyListingsSub;
    @FXML private Label    lblEmptyContractsTitle, lblEmptyContractsSub;
    @FXML private Button   btnAddListing, btnEmptyAdd, tabListings, tabContracts;

    // Stats
    @FXML private Label lblStatTotal, lblStatActive, lblStatContracts, lblStatEarnings;
    @FXML private Label lblStatTotalTitle, lblStatActiveTitle, lblStatContractsTitle, lblStatEarningsTitle;

    private List<File> selectedImages = new ArrayList<>();
    // Per-dialog image list (reset each time dialog opens)
    private List<File> dialogImages = new ArrayList<>();
    
    private JSONArray currentListings = new JSONArray();
    private JSONArray currentContracts = new JSONArray();

    @Override
    public void initialize(URL location, ResourceBundle resources) {
        translateUI();
        if (!AuthService.getInstance().isLoggedIn()) {
            NavigationService.getInstance().navigateTo("Login.fxml");
            return;
        }
        loadMyListings();

        // Check for quick action flag
        Object data = NavigationService.getInstance().consumeData();
        if ("OPEN_ADD_MODAL".equals(data)) {
            Platform.runLater(this::openAddDialog);
        }
    }

    private void translateUI() {
        if (lblOwnerTitle != null) lblOwnerTitle.setText("🏗️  " + I18n.t("owner.title"));
        if (lblOwnerSubtitle != null) lblOwnerSubtitle.setText(I18n.t("owner.subtitle"));
        if (btnAddListing != null) btnAddListing.setText(I18n.t("owner.add_listing"));
        if (tabListings != null) tabListings.setText(I18n.t("owner.tab.listings"));
        if (tabContracts != null) tabContracts.setText(I18n.t("owner.tab.contracts"));
        if (lblLoading != null) lblLoading.setText(I18n.t("owner.loading"));
        if (lblEmptyListingsTitle != null) lblEmptyListingsTitle.setText(I18n.t("owner.empty.title"));
        if (lblEmptyListingsSub != null) lblEmptyListingsSub.setText(I18n.t("owner.empty.subtitle"));
        if (btnEmptyAdd != null) btnEmptyAdd.setText(I18n.t("owner.empty.btn"));
        if (lblEmptyContractsTitle != null) lblEmptyContractsTitle.setText(I18n.t("owner.empty.contracts.title"));
        if (lblEmptyContractsSub != null) lblEmptyContractsSub.setText(I18n.t("owner.empty.contracts.subtitle"));
        
        // Stats titles
        if (lblStatTotalTitle != null) lblStatTotalTitle.setText(I18n.t("owner.stats.total_listings"));
        if (lblStatActiveTitle != null) lblStatActiveTitle.setText(I18n.t("owner.stats.active_listings"));
        if (lblStatContractsTitle != null) lblStatContractsTitle.setText(I18n.t("owner.stats.total_contracts"));
        if (lblStatEarningsTitle != null) lblStatEarningsTitle.setText(I18n.t("owner.stats.total_earnings"));
    }

    // ─── Tabs ─────────────────────────────────────────────
    @FXML
    private void showListings() {
        listingsPane.setVisible(true);  listingsPane.setManaged(true);
        contractsPane.setVisible(false); contractsPane.setManaged(false);
        tabListings.getStyleClass().add("active");
        tabContracts.getStyleClass().remove("active");
        loadMyListings();
    }

    @FXML
    private void showContracts() {
        contractsPane.setVisible(true);  contractsPane.setManaged(true);
        listingsPane.setVisible(false);  listingsPane.setManaged(false);
        tabContracts.getStyleClass().add("active");
        tabListings.getStyleClass().remove("active");
        loadContracts();
    }

    // ─── Load listings ─────────────────────────────────────
    private void loadMyListings() {
        setLoading(true);
        new Thread(() -> {
            try {
                JSONObject resp = ApiClient.getInstance().get("/listings.php");
                JSONArray arr = resp.optJSONArray("listings");
                if (arr == null) arr = new JSONArray();
                final JSONArray listings = arr;
                Platform.runLater(() -> {
                    setLoading(false);
                    listingsGrid.getChildren().clear();
                    if (listings.isEmpty()) {
                        emptyListingsPane.setVisible(true);
                        emptyListingsPane.setManaged(true);
                        return;
                    }
                    emptyListingsPane.setVisible(false);
                    emptyListingsPane.setManaged(false);
                    currentListings = listings;
                    updateStatistics();
                    for (int i = 0; i < listings.length(); i++) {
                        listingsGrid.getChildren().add(buildListingCard(listings.getJSONObject(i)));
                    }
                });
            } catch (Exception e) {
                e.printStackTrace();
                Platform.runLater(() -> setLoading(false));
            }
        }).start();
    }

    private VBox buildListingCard(JSONObject l) {
        int    id     = l.optInt("id", 0);
        String title  = l.optString("title", "Untitled");
        String addr   = l.optString("address", "");
        double price  = l.optDouble("price", 0);
        String status = l.optString("status", "active");

        String thumb = l.optString("thumbnail", "");

        VBox card = new VBox(0);
        card.setStyle("-fx-background-color: white; -fx-background-radius: 14; " +
                      "-fx-effect: dropshadow(three-pass-box, rgba(0,0,0,0.07), 12, 0, 0, 3); " +
                      "-fx-pref-width: 280;");

        javafx.scene.layout.StackPane imgPane = new javafx.scene.layout.StackPane();
        imgPane.setMinHeight(160); imgPane.setMaxHeight(160);
        Region img = new Region();
        img.setStyle("-fx-background-color: linear-gradient(135deg, #e0e7ff, #ede9fe); " +
                     "-fx-background-radius: 14 14 0 0; -fx-min-height: 160;");
        imgPane.getChildren().add(img);
        if (!thumb.isEmpty()) {
            String type = l.optString("property_type", "apartment");
            String fullUrl = ApiClient.resolveImageUrl(thumb, title, type);
            CreativeImageLoader.load(imgPane, fullUrl, 280, 160, title, type);
        }

        VBox info = new VBox(6);
        info.setStyle("-fx-padding: 14 16 16 16;");

        Label lTitle = new Label(title);
        lTitle.setStyle("-fx-font-weight: bold; -fx-font-size: 14px; -fx-text-fill: #0f172a;");
        lTitle.setWrapText(true);

        Label lAddr = new Label("📍 " + addr);
        lAddr.setStyle("-fx-text-fill: #64748b; -fx-font-size: 12px;");

        HBox priceRow = new HBox(8);
        priceRow.setAlignment(Pos.CENTER_LEFT);
        Label lPrice = new Label(price + " " + I18n.t("detail.price_month"));
        lPrice.setStyle("-fx-text-fill: #4f46e5; -fx-font-weight: bold; -fx-font-size: 14px;");
        HBox.setHgrow(lPrice, Priority.ALWAYS);
        String statusTxt = status.equalsIgnoreCase("active") ? I18n.t("owner.status.active") : I18n.t("owner.status.inactive");
        Label lStatus = new Label(statusTxt);
        lStatus.getStyleClass().addAll("badge", listingStatusBadge(status));
        priceRow.getChildren().addAll(lPrice, lStatus);

        HBox btns = new HBox(8);
        btns.setStyle("-fx-padding: 6 0 0 0;");
        Button btnView = new Button("👁 " + I18n.t("admin.view"));
        btnView.getStyleClass().addAll("btn-secondary", "btn-sm");
        btnView.setOnAction(e -> NavigationService.getInstance().navigateTo("ListingDetail.fxml", id));
        Button btnEdit = new Button("✏️ " + I18n.t("owner.edit"));
        btnEdit.getStyleClass().addAll("btn-ghost", "btn-sm");
        btnEdit.setOnAction(e -> openEditDialog(l));
        Button btnDelete = new Button("🗑");
        btnDelete.getStyleClass().addAll("btn-danger", "btn-sm");
        btnDelete.setOnAction(e -> deleteListing(id));
        btns.getChildren().addAll(btnView, btnEdit, btnDelete);

        info.getChildren().addAll(lTitle, lAddr, priceRow, btns);
        card.getChildren().addAll(imgPane, info);
        return card;
    }

    // ─── Load contracts ────────────────────────────────────
    private void loadContracts() {
        new Thread(() -> {
            try {
                JSONObject resp = ApiClient.getInstance().get("/contracts.php?action=user-contracts");
                JSONArray arr = resp.optJSONArray("contracts");
                Platform.runLater(() -> {
                    contractsList.getChildren().clear();
                    if (arr == null || arr.isEmpty()) {
                        emptyContractsPane.setVisible(true);
                        emptyContractsPane.setManaged(true);
                        return;
                    }
                    emptyContractsPane.setVisible(false);
                    emptyContractsPane.setManaged(false);
                    currentContracts = arr;
                    updateStatistics();
                    for (int i = 0; i < arr.length(); i++) {
                        contractsList.getChildren().add(buildContractRow(arr.getJSONObject(i)));
                    }
                });
            } catch (Exception e) { e.printStackTrace(); }
        }).start();
    }

    private HBox buildContractRow(JSONObject c) {
        HBox row = new HBox(16);
        row.getStyleClass().add("contract-row");
        row.setAlignment(Pos.CENTER_LEFT);

        Label icon = new Label("📋");
        icon.setStyle("-fx-font-size: 22px;");

        VBox info = new VBox(3);
        HBox.setHgrow(info, Priority.ALWAYS);
        Label title = new Label(String.format(I18n.t("admin.contract_no"), c.optInt("id", 0)) + " — " + c.optString("listing_title", "Property"));
        title.setStyle("-fx-font-weight: bold; -fx-text-fill: #0f172a; -fx-font-size: 13px;");
        Label sub = new Label(String.format(I18n.t("owner.tenant"), c.optString("student_name", I18n.t("common.unknown")))
                + "  ·  " + c.optInt("monthly_rent", 0) + " " + I18n.t("detail.price_month"));
        sub.setStyle("-fx-text-fill: #64748b; -fx-font-size: 12px;");
        info.getChildren().addAll(title, sub);

        String status = c.optString("status", "pending");
        Label badge = new Label(ContractStatusUi.formatLabel(status));
        badge.getStyleClass().addAll("badge", ContractStatusUi.badgeClass(status));

        int cId = c.optInt("id", 0);
        Button btnView = new Button("👁");
        btnView.getStyleClass().addAll("btn-ghost", "btn-sm");
        btnView.setOnAction(e -> ContractViewer.show(cId));

        row.getChildren().addAll(icon, info, badge, btnView);
        return row;
    }

    // ─── Add Listing Dialog ────────────────────────────────
    @FXML
    private void openAddDialog() {
        openListingForm(null);
    }

    private void openEditDialog(JSONObject listing) {
        openListingForm(listing);
    }

    private void openListingForm(JSONObject existing) {
        boolean isEdit = existing != null;
        dialogImages = new ArrayList<>();  // reset per dialog
        Stage dialog = new Stage();
        dialog.initModality(Modality.APPLICATION_MODAL);
        dialog.setTitle(isEdit ? I18n.t("owner.form.edit_title") : I18n.t("owner.form.add_title"));
        dialog.setResizable(false);

        ScrollPane scroll = new ScrollPane();
        scroll.setFitToWidth(true);
        VBox root = new VBox(16);
        root.setStyle("-fx-padding: 32; -fx-background-color: white;");
        root.setPrefWidth(540);

        Label dlgTitle = new Label(isEdit ? "✏️  " + I18n.t("owner.form.edit_title") : "➕  " + I18n.t("owner.form.add_title"));
        dlgTitle.setStyle("-fx-font-size: 20px; -fx-font-weight: bold; -fx-text-fill: #0f172a;");
        root.getChildren().add(dlgTitle);

        // Form fields
        TextField fTitle    = formField(I18n.t("owner.form.title"),         existing != null ? existing.optString("title") : "");
        TextField fAddress  = formField(I18n.t("owner.form.address"),       existing != null ? existing.optString("address") : "");
        TextField fPrice    = formField(I18n.t("owner.form.price"),         existing != null ? String.valueOf(existing.optDouble("price", 0)) : "");
        TextField fBedrooms = formField(I18n.t("owner.form.bedrooms"),      existing != null ? String.valueOf(existing.optInt("bedrooms", 1)) : "");
        TextField fBaths    = formField(I18n.t("owner.form.bathrooms"),     existing != null ? String.valueOf(existing.optInt("bathrooms", 1)) : "");
        TextField fCapacity = formField(I18n.t("owner.form.capacity"),      existing != null ? String.valueOf(existing.optInt("capacity", 1)) : "");

        ComboBox<String> cbType = new ComboBox<>();
        cbType.getItems().addAll("apartment", "studio", "villa", "room", "shared");
        cbType.setValue(existing != null ? existing.optString("property_type", "apartment") : "apartment");
        cbType.setMaxWidth(Double.MAX_VALUE);
        cbType.getStyleClass().add("combo-box");

        ComboBox<String> cbGender = new ComboBox<>();
        cbGender.getItems().addAll("male", "female", "mixed", "no_preference");
        cbGender.setValue(existing != null ? existing.optString("gender_preference", "mixed") : "mixed");
        cbGender.setMaxWidth(Double.MAX_VALUE);
        cbGender.getStyleClass().add("combo-box");

        TextArea fDesc = new TextArea(existing != null ? existing.optString("description", "") : "");
        fDesc.setPromptText(I18n.t("owner.form.description_placeholder"));
        fDesc.setPrefHeight(80);
        fDesc.setWrapText(true);
        fDesc.getStyleClass().add("text-area");

        // Signature Pad
        Label sigLabel = new Label(I18n.t("owner.form.signature"));
        sigLabel.getStyleClass().add("form-label");
        SignaturePad sigPad = new SignaturePad(476, 120);
        Button btnClearSig = new Button(I18n.t("clear"));
        btnClearSig.getStyleClass().add("btn-ghost");
        btnClearSig.setOnAction(e -> sigPad.clear());
        VBox sigBox = new VBox(8, sigLabel, sigPad, btnClearSig);
        // ── Image gallery picker ──────────────────────────────
        Label imgLabel = new Label(I18n.t("owner.form.no_images"));
        imgLabel.setStyle("-fx-text-fill: #64748b; -fx-font-size: 12px;");

        FlowPane galleryPane = new FlowPane(8, 8);
        galleryPane.setPrefWrapLength(476);

        Button imgBtn = new Button("📷  " + I18n.t("owner.form.images_btn"));
        imgBtn.getStyleClass().addAll("btn-secondary", "btn-sm");
        imgBtn.setOnAction(e -> {
            FileChooser fc = new FileChooser();
            fc.getExtensionFilters().add(
                    new FileChooser.ExtensionFilter("Images", "*.jpg", "*.jpeg", "*.png", "*.webp"));
            fc.setTitle("Select Property Images");
            List<File> files = fc.showOpenMultipleDialog(dialog);
            if (files != null) {
                dialogImages.addAll(files);
                imgLabel.setText(String.format(I18n.t("owner.form.images_selected"), dialogImages.size()));
                imgLabel.setStyle("-fx-text-fill: #16a34a; -fx-font-weight: bold;");
                refreshGalleryPane(galleryPane, dialogImages, imgLabel, dialog);
            }
        });

        VBox alertErr = new VBox();
        alertErr.getStyleClass().add("alert-error");
        alertErr.setVisible(false); alertErr.setManaged(false);
        Label errLbl = new Label();
        errLbl.getStyleClass().add("alert-error-text");
        alertErr.getChildren().add(errLbl);

        root.getChildren().addAll(
                fTitle, fAddress,
                new HBox(10, fPrice, fBedrooms, fBaths, fCapacity),
                labeledField(I18n.t("owner.form.type"), cbType),
                labeledField(I18n.t("owner.form.gender"), cbGender),
                labeledField(I18n.t("owner.form.description"), fDesc),
                sigBox,
                labeledField(I18n.t("owner.form.images"), new VBox(6, imgBtn, imgLabel, galleryPane)),
                alertErr
        );

        Button saveBtn = new Button(isEdit ? I18n.t("owner.form.save") : I18n.t("owner.form.publish"));
        saveBtn.getStyleClass().add("btn-primary");
        saveBtn.setMaxWidth(Double.MAX_VALUE);
        saveBtn.setPrefHeight(48);
        saveBtn.setOnAction(e -> {
            alertErr.setVisible(false); alertErr.setManaged(false);
            String t = fTitle.getText().trim();
            String a = fAddress.getText().trim();
            String p = fPrice.getText().trim();
            if (t.isEmpty() || a.isEmpty() || p.isEmpty()) {
                errLbl.setText("⚠️  " + I18n.t("owner.form.required_fields"));
                alertErr.setVisible(true); alertErr.setManaged(true);
                return;
            }
            saveBtn.setDisable(true);
            saveBtn.setText(I18n.t("owner.form.saving"));
            int editId = existing != null ? existing.optInt("id", 0) : 0;
            new Thread(() -> {
                try {
                    JSONObject body = new JSONObject()
                            .put("title",              t)
                            .put("address",            a)
                            .put("price",              Double.parseDouble(p))
                            .put("bedrooms",           intOrDefault(fBedrooms.getText(), 1))
                            .put("bathrooms",          intOrDefault(fBaths.getText(), 1))
                            .put("capacity",           intOrDefault(fCapacity.getText(), 1))
                            .put("property_type",      cbType.getValue())
                            .put("gender_preference",  cbGender.getValue())
                            .put("description",        fDesc.getText())
                            .put("owner_signature",    sigPad.toBase64());

                    JSONObject resp;
                    if (isEdit) {
                        resp = ApiClient.getInstance().put("/listings.php?id=" + editId, body);
                    } else {
                        resp = ApiClient.getInstance().post("/listings.php", body);
                    }

                    // Upload images if any were selected (create only, or edit with new images)
                    if (!dialogImages.isEmpty() && (resp.has("id") || resp.has("listing_id") || resp.optBoolean("success", false))) {
                        int newId = resp.optInt("id", resp.optInt("listing_id", editId));
                        if (newId > 0) {
                            String[] fieldNames = new String[dialogImages.size()];
                            File[]   files      = new File[dialogImages.size()];
                            for (int ii = 0; ii < dialogImages.size(); ii++) {
                                fieldNames[ii] = "images[]";
                                files[ii]      = dialogImages.get(ii);
                            }
                            try {
                                ApiClient.getInstance().uploadFiles(
                                        "/listings.php?action=upload_images&listing_id=" + newId,
                                        fieldNames, files);
                            } catch (Exception imgEx) {
                                System.err.println("[OwnerListings] Image upload failed: " + imgEx.getMessage());
                            }
                        }
                    }

                    final boolean ok = resp.has("id") || resp.has("listing_id") || resp.optBoolean("success", false);
                    Platform.runLater(() -> {
                        if (ok) {
                            dialog.close();
                            showSuccess(isEdit ? I18n.t("owner.form.success_edit") : I18n.t("owner.form.success_add"));
                            loadMyListings();
                        } else {
                            errLbl.setText("⚠️  " + resp.optString("error", "Save failed."));
                            alertErr.setVisible(true); alertErr.setManaged(true);
                            saveBtn.setDisable(false);
                            saveBtn.setText(isEdit ? "Save Changes" : "Publish Listing");
                        }
                    });
                } catch (Exception ex) {
                    ex.printStackTrace();
                    Platform.runLater(() -> {
                        errLbl.setText("⚠️  Connection error. Please try again.");
                        alertErr.setVisible(true); alertErr.setManaged(true);
                        saveBtn.setDisable(false);
                        saveBtn.setText(isEdit ? "Save Changes" : "Publish Listing");
                    });
                }
            }).start();
        });
        root.getChildren().add(saveBtn);

        scroll.setContent(root);
        Scene scene = new Scene(scroll, 560, 680);
        scene.getStylesheets().add(getClass().getResource("/css/unidar-pages.css").toExternalForm());
        dialog.setScene(scene);
        dialog.show();
    }

    private void deleteListing(int id) {
        Alert confirm = new Alert(Alert.AlertType.CONFIRMATION,
                I18n.t("owner.delete.confirm"),
                ButtonType.YES, ButtonType.CANCEL);
        confirm.setTitle(I18n.t("owner.delete.title"));
        confirm.setHeaderText(String.format(I18n.t("owner.delete.header"), id));
        confirm.showAndWait().ifPresent(btn -> {
            if (btn == ButtonType.YES) {
                new Thread(() -> {
                    try {
                        ApiClient.getInstance().delete("/listings.php?id=" + id);
                        Platform.runLater(() -> {
                            showSuccess(I18n.t("owner.delete.success"));
                            loadMyListings();
                        });
                    } catch (Exception e) { e.printStackTrace(); }
                }).start();
            }
        });
    }

    // ─── Helpers ──────────────────────────────────────────
    private void updateStatistics() {
        int totalListings = currentListings != null ? currentListings.length() : 0;
        int activeListings = 0;
        if (currentListings != null) {
            for (int i = 0; i < currentListings.length(); i++) {
                if ("active".equalsIgnoreCase(currentListings.getJSONObject(i).optString("status"))) {
                    activeListings++;
                }
            }
        }

        int totalContracts = currentContracts != null ? currentContracts.length() : 0;
        double totalEarnings = 0;
        if (currentContracts != null) {
            for (int i = 0; i < currentContracts.length(); i++) {
                JSONObject c = currentContracts.getJSONObject(i);
                String status = c.optString("status", "");
                // If paid or active, it contributes to earnings
                if ("active".equalsIgnoreCase(status) || "completed".equalsIgnoreCase(status) || "paid".equalsIgnoreCase(status)) {
                    totalEarnings += c.optDouble("monthly_rent", 0);
                }
            }
        }

        final int fActive = activeListings;
        final double fEarnings = totalEarnings;
        Platform.runLater(() -> {
            if (lblStatTotal != null) lblStatTotal.setText(String.valueOf(totalListings));
            if (lblStatActive != null) lblStatActive.setText(String.valueOf(fActive));
            if (lblStatContracts != null) lblStatContracts.setText(String.valueOf(totalContracts));
            if (lblStatEarnings != null) lblStatEarnings.setText(String.format("%.0f TND", fEarnings));
        });
    }

    private TextField formField(String prompt, String value) {
        TextField tf = new TextField(value);
        tf.setPromptText(prompt);
        tf.getStyleClass().add("form-input");
        tf.setPrefHeight(42);
        HBox.setHgrow(tf, Priority.ALWAYS);
        return tf;
    }

    private VBox labeledField(String label, javafx.scene.Node field) {
        VBox box = new VBox(6);
        Label l = new Label(label);
        l.getStyleClass().add("form-label");
        box.getChildren().addAll(l, field);
        return box;
    }

    private void setLoading(boolean loading) {
        loadingPane.setVisible(loading);
        loadingPane.setManaged(loading);
    }

    private void showSuccess(String msg) {
        lblSuccess.setText("✅  " + msg);
        alertSuccess.setVisible(true); alertSuccess.setManaged(true);
    }

    private int intOrDefault(String s, int def) {
        try { return Integer.parseInt(s.trim()); } catch (Exception e) { return def; }
    }

    /** Rebuild the gallery thumbnail strip from the dialogImages list. */
    private void refreshGalleryPane(FlowPane pane, List<File> images, Label countLabel, Stage owner) {
        pane.getChildren().clear();
        for (int i = 0; i < images.size(); i++) {
            final int idx = i;
            final File f  = images.get(i);

            StackPane cell = new StackPane();
            cell.setPrefSize(76, 60);
            cell.setStyle("-fx-background-color: #f1f5f9; -fx-background-radius: 8;");

            try {
                ImageView iv = new ImageView(new Image(f.toURI().toString(), 76, 60, true, true));
                iv.setFitWidth(76); iv.setFitHeight(60); iv.setPreserveRatio(false);
                cell.getChildren().add(iv);
            } catch (Exception ignored) {
                Label ic = new Label("🖼");
                ic.setStyle("-fx-font-size: 20px;");
                cell.getChildren().add(ic);
            }

            // Red ✕ removal button
            Button rm = new Button("✕");
            rm.setStyle("-fx-background-color: rgba(220,38,38,0.85); -fx-text-fill: white; " +
                        "-fx-font-size: 10px; -fx-padding: 2 5; -fx-background-radius: 50; " +
                        "-fx-cursor: hand;");
            StackPane.setAlignment(rm, javafx.geometry.Pos.TOP_RIGHT);
            rm.setOnAction(e -> {
                images.remove(idx);
                countLabel.setText(images.isEmpty()
                        ? I18n.t("owner.form.no_images")
                        : String.format(I18n.t("owner.form.images_selected"), images.size()));
                countLabel.setStyle(images.isEmpty()
                        ? "-fx-text-fill: #64748b; -fx-font-size: 12px;"
                        : "-fx-text-fill: #16a34a; -fx-font-weight: bold;");
                refreshGalleryPane(pane, images, countLabel, owner);
            });
            cell.getChildren().add(rm);
            pane.getChildren().add(cell);
        }
    }

    /** Listing publish status (active / inactive), not contract status. */
    private String listingStatusBadge(String status) {
        return switch (status.toLowerCase()) {
            case "active", "approved" -> "badge-success";
            case "pending", "inactive", "draft" -> "badge-warning";
            case "cancelled", "rejected" -> "badge-error";
            default -> "badge-neutral";
        };
    }
}
