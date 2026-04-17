package tn.unidar.desktop.utils;

/**
 * Contract status values from the API/DB (e.g. signed_by_student, signed_by_owner, signed_by_both).
 * UI helpers keep labels and actions aligned with {@code contracts} table enums.
 */
public final class ContractStatusUi {

    private ContractStatusUi() {}

    /** Human-readable label for status strings with underscores. */
    public static String formatLabel(String status) {
        if (status == null || status.isEmpty()) return "—";
        String[] parts = status.split("_");
        StringBuilder sb = new StringBuilder();
        for (int i = 0; i < parts.length; i++) {
            if (i > 0) sb.append(' ');
            String p = parts[i];
            if (!p.isEmpty()) {
                sb.append(Character.toUpperCase(p.charAt(0))).append(p.substring(1).toLowerCase());
            }
        }
        return sb.toString();
    }

    public static String badgeClass(String status) {
        if (status == null) return "badge-neutral";
        String s = status.toLowerCase();
        return switch (s) {
            case "active", "paid", "completed" -> "badge-success";
            case "signed_by_student", "signed_by_owner", "signed_by_both",
                 "pending_signature", "draft" -> "badge-warning";
            case "cancelled", "rejected" -> "badge-error";
            default -> "badge-neutral";
        };
    }

    /** True when the contract is signed (per DB) but not yet moved to active/paid by payment flow. */
    public static boolean needsPayment(String status) {
        if (status == null) return false;
        return switch (status.toLowerCase()) {
            case "signed_by_student", "signed_by_owner", "signed_by_both" -> true;
            default -> false;
        };
    }

    public static boolean canRequestTermination(String status) {
        if (status == null) return false;
        return switch (status.toLowerCase()) {
            // Allow termination as soon as any signature exists or contract is active/paid
            case "signed_by_student", "signed_by_owner", "signed_by_both",
                 "active", "paid" -> true;
            default -> false;
        };
    }

    /** Returns true if a termination request is currently pending in the DB for this contract. */
    public static boolean isTerminationPending(org.json.JSONObject contract) {
        if (contract == null) return false;
        String termStatus = contract.optString("termination_status", "");
        return "pending".equalsIgnoreCase(termStatus);
    }
}
