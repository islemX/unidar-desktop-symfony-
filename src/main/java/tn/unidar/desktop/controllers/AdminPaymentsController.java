package tn.unidar.desktop.controllers;

import javafx.application.Platform;
import javafx.beans.property.*;
import javafx.collections.FXCollections;
import javafx.collections.ObservableList;
import javafx.fxml.FXML;
import javafx.fxml.Initializable;
import javafx.scene.control.*;
import org.json.JSONArray;
import org.json.JSONObject;
import tn.unidar.desktop.services.ApiClient;
import tn.unidar.desktop.services.NavigationService;

import java.net.URL;
import java.util.ResourceBundle;

public class AdminPaymentsController implements Initializable {

    @FXML private Label lblTotalRevenue, lblTotalComm, lblTotalTrans;
    @FXML private TextField txtSearch;
    @FXML private TableView<PaymentRecord> tablePayments;
    @FXML private TableColumn<PaymentRecord, String> colDate, colUser, colListing, colMethod, colStatus;
    @FXML private TableColumn<PaymentRecord, Double> colAmount, colComm;

    private ObservableList<PaymentRecord> masterData = FXCollections.observableArrayList();

    @Override
    public void initialize(URL location, ResourceBundle resources) {
        setupTable();
        loadStats();
        loadPayments();
        
        txtSearch.textProperty().addListener((obs, old, val) -> filterData(val));
    }

    private void setupTable() {
        colDate.setCellValueFactory(d -> d.getValue().dateProperty());
        colUser.setCellValueFactory(d -> d.getValue().userProperty());
        colListing.setCellValueFactory(d -> d.getValue().listingProperty());
        colMethod.setCellValueFactory(d -> d.getValue().methodProperty());
        colStatus.setCellValueFactory(d -> d.getValue().statusProperty());
        colAmount.setCellValueFactory(d -> d.getValue().amountProperty().asObject());
        colComm.setCellValueFactory(d -> d.getValue().commProperty().asObject());
        
        // Custom cell factory for status (badge style)
        colStatus.setCellFactory(column -> new TableCell<>() {
            @Override
            protected void updateItem(String item, boolean empty) {
                super.updateItem(item, empty);
                if (empty || item == null) {
                    setText(null);
                    setGraphic(null);
                } else {
                    setText(item.toUpperCase());
                    setStyle("-fx-font-weight: bold; -fx-alignment: center;");
                    if (item.equalsIgnoreCase("completed") || item.equalsIgnoreCase("paid")) {
                        setTextFill(javafx.scene.paint.Color.web("#059669"));
                    } else if (item.equalsIgnoreCase("pending")) {
                        setTextFill(javafx.scene.paint.Color.web("#d97706"));
                    } else {
                        setTextFill(javafx.scene.paint.Color.web("#dc2626"));
                    }
                }
            }
        });
    }

    @FXML
    private void loadStats() {
        new Thread(() -> {
            try {
                JSONObject resp = ApiClient.getInstance().get("/admin-payments.php?action=statistics&period=year");
                if (resp != null && resp.optBoolean("success")) {
                    JSONObject stats = resp.getJSONObject("stats");
                    Platform.runLater(() -> {
                        lblTotalRevenue.setText(String.format("%.2f TND", stats.optDouble("total_revenue", 0)));
                        lblTotalComm.setText(String.format("%.2f TND", stats.optDouble("total_commissions", 0)));
                        lblTotalTrans.setText(String.valueOf(stats.optInt("active_contracts", 0)));
                    });
                }
            } catch (Exception e) { e.printStackTrace(); }
        }).start();
    }

    @FXML
    private void loadPayments() {
        new Thread(() -> {
            try {
                JSONObject resp = ApiClient.getInstance().get("/admin-payments.php?action=payments&period=year");
                if (resp != null && resp.optBoolean("success")) {
                    JSONArray arr = resp.getJSONArray("payments");
                    ObservableList<PaymentRecord> list = FXCollections.observableArrayList();
                    for (int i = 0; i < arr.length(); i++) {
                        list.add(new PaymentRecord(arr.getJSONObject(i)));
                    }
                    Platform.runLater(() -> {
                        masterData.setAll(list);
                        tablePayments.setItems(list);
                    });
                }
            } catch (Exception e) { e.printStackTrace(); }
        }).start();
    }

    private void filterData(String val) {
        if (val == null || val.isEmpty()) {
            tablePayments.setItems(masterData);
            return;
        }
        String lower = val.toLowerCase();
        tablePayments.setItems(masterData.filtered(p -> 
            p.getUser().toLowerCase().contains(lower) || 
            p.getListing().toLowerCase().contains(lower) ||
            p.getStatus().toLowerCase().contains(lower)
        ));
    }

    @FXML
    private void gotoDashboard() {
        NavigationService.getInstance().navigateTo("AdminDashboard.fxml");
    }

    // --- Inner Model ---
    public static class PaymentRecord {
        private final StringProperty date, user, listing, method, status;
        private final DoubleProperty amount, comm;

        public PaymentRecord(JSONObject obj) {
            this.date = new SimpleStringProperty(obj.optString("created_at").split(" ")[0]);
            this.user = new SimpleStringProperty(obj.optString("student_name", "Unknown"));
            this.listing = new SimpleStringProperty(obj.optString("listing_title", "N/A"));
            this.method = new SimpleStringProperty(obj.optString("payment_method", "card"));
            this.status = new SimpleStringProperty(obj.optString("status", "pending"));
            this.amount = new SimpleDoubleProperty(obj.optDouble("amount", 0));
            this.comm = new SimpleDoubleProperty(obj.optDouble("commission", 0));
        }

        public StringProperty dateProperty() { return date; }
        public StringProperty userProperty() { return user; }
        public StringProperty listingProperty() { return listing; }
        public StringProperty methodProperty() { return method; }
        public StringProperty statusProperty() { return status; }
        public DoubleProperty amountProperty() { return amount; }
        public DoubleProperty commProperty() { return comm; }
        
        public String getUser() { return user.get(); }
        public String getListing() { return listing.get(); }
        public String getStatus() { return status.get(); }
    }
}
