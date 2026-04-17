package tn.unidar.desktop.services;

import org.json.JSONArray;
import org.json.JSONObject;

import java.io.*;
import java.net.HttpURLConnection;
import java.net.URL;
import java.nio.charset.StandardCharsets;
import java.nio.file.Files;
import java.util.List;
import java.util.Map;

public class ApiClient {
    private static final String BASE_URL = "http://localhost/unidar/backend/api";
    private static final String STORAGE_BASE_URL = "http://localhost/unidar";

    public static String resolveImageUrl(String path) {
        return resolveImageUrl(path, "", "");
    }

    public static String resolveImageUrl(String path, String title, String type) {
        if (path == null || path.isEmpty()) {
            return BASE_URL + "/image_proxy.php?p=&title=" + urlEncode(title) + "&type=" + urlEncode(type);
        }
        if (path.startsWith("http") || path.startsWith("data:")) return path;
        
        // Handle cloud storage prefixes as seen in scraper.php
        if (path.startsWith("hounitn_cdn/")) {
            return "https://storage.googleapis.com/" + path;
        }
        if (path.startsWith("hounitn/")) {
            return "https://storage.googleapis.com/hounitn_cdn/" + path.substring(8);
        }

        // Use the backend image proxy for local uploads
        // This ensures beautiful SVG placeholders are served if the file is missing
        String cleanPath = path.startsWith("/") ? path.substring(1) : path;
        return BASE_URL + "/image_proxy.php?p=" + urlEncode(cleanPath) + 
               "&title=" + urlEncode(title) + "&type=" + urlEncode(type);
    }

    private static String urlEncode(String s) {
        try {
            return java.net.URLEncoder.encode(s != null ? s : "", "UTF-8");
        } catch (Exception e) { return ""; }
    }

    /**
     * Raw JWT value extracted from Set-Cookie: unidar_token on login.
     * Sent as Cookie: unidar_token=<value> on every subsequent request.
     */
    private String authToken;
    private static ApiClient instance;

    private ApiClient() {}

    public static ApiClient getInstance() {
        if (instance == null) instance = new ApiClient();
        return instance;
    }

    /** Store the raw token value (called by legacy code or tests). */
    public void setAuthToken(String token) { this.authToken = token; }

    /** Clear auth on logout. */
    public void clearAuth() { this.authToken = null; }

    // ── GET ───────────────────────────────────────────────
    public JSONObject get(String endpoint) throws Exception {
        HttpURLConnection conn = openConnection(endpoint, "GET");
        return readResponse(conn);
    }

    // ── POST ──────────────────────────────────────────────
    public JSONObject post(String endpoint, JSONObject body) throws Exception {
        HttpURLConnection conn = openConnection(endpoint, "POST");
        conn.setDoOutput(true);
        conn.setRequestProperty("Content-Type", "application/json");
        try (OutputStream os = conn.getOutputStream()) {
            os.write(body.toString().getBytes(StandardCharsets.UTF_8));
        }
        return readResponse(conn);
    }

    /** @deprecated Use post() — cookie capture is now automatic via readResponse(). */
    public JSONObject postAndCaptureCookie(String endpoint, JSONObject body) throws Exception {
        return post(endpoint, body);
    }

    // ── PUT ───────────────────────────────────────────────
    public JSONObject put(String endpoint, JSONObject body) throws Exception {
        System.out.println("[ApiClient] PUT " + endpoint);
        HttpURLConnection conn = openConnection(endpoint, "PUT");
        conn.setDoOutput(true);
        conn.setRequestProperty("Content-Type", "application/json");
        try (OutputStream os = conn.getOutputStream()) {
            os.write(body.toString().getBytes(StandardCharsets.UTF_8));
        }
        return readResponse(conn);
    }

    // ── DELETE ────────────────────────────────────────────
    public JSONObject delete(String endpoint) throws Exception {
        HttpURLConnection conn = openConnection(endpoint, "DELETE");
        return readResponse(conn);
    }

    // ── FETCH BYTES (Images, etc) ─────────────────────────
    public byte[] fetchBytes(String urlStr) throws Exception {
        if (urlStr == null || urlStr.isEmpty()) return null;
        
        // If it's a data URI, decode it immediately
        if (urlStr.startsWith("data:")) {
            try {
                String base64Data = urlStr.substring(urlStr.indexOf(",") + 1)
                        .replace("\\/", "/")
                        .replaceAll("[^A-Za-z0-9+/=]", "");
                
                // Ensure proper padding for the decoder
                int padCount = (4 - (base64Data.length() % 4)) % 4;
                if (padCount > 0) base64Data += "=".repeat(padCount);
                
                // If it's still invalid, it might be truncated. 
                // We'll try to decode as much as possible or at least catch the exception.
                try {
                    return java.util.Base64.getMimeDecoder().decode(base64Data);
                } catch (IllegalArgumentException e) {
                    // Try to fix truncation by removing the last 1-2 chars and re-padding
                    if (base64Data.length() > 4) {
                        String truncated = base64Data.substring(0, base64Data.length() - (base64Data.length() % 4));
                        return java.util.Base64.getMimeDecoder().decode(truncated);
                    }
                    throw e;
                }
            } catch (Exception e) {
                System.err.println("[ApiClient] Failed to decode Data URI: " + e.getMessage());
                return null;
            }
        }

        URL url = new URL(urlStr);
        HttpURLConnection conn = (HttpURLConnection) url.openConnection();
        conn.setRequestMethod("GET");
        conn.setConnectTimeout(20_000);
        conn.setReadTimeout(30_000);
        
        String userAgent = "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36";
        conn.setRequestProperty("User-Agent", userAgent);
        conn.setRequestProperty("Accept", "image/avif,image/webp,image/apng,image/*,*/*;q=0.8");

        // Send auth cookies if it's our own storage/server
        if (urlStr.contains("localhost") || urlStr.contains("unidar")) {
            if (authToken != null && !authToken.isEmpty()) {
                if (authToken.length() < 50) {
                    conn.setRequestProperty("Cookie", "PHPSESSID=" + authToken);
                } else {
                    conn.setRequestProperty("Cookie", "unidar_token=" + authToken);
                }
            }
        }

        int status = conn.getResponseCode();
        if (status >= 400) {
            throw new IOException("Server returned " + status + " for " + urlStr);
        }

        String encoding = conn.getContentEncoding();
        InputStream is = conn.getInputStream();
        if (encoding != null && encoding.equalsIgnoreCase("gzip")) {
            is = new java.util.zip.GZIPInputStream(is);
        }

        try (InputStream finalIs = is; 
             ByteArrayOutputStream bos = new ByteArrayOutputStream()) {
            byte[] buffer = new byte[8192];
            int read;
            while ((read = finalIs.read(buffer)) != -1) {
                bos.write(buffer, 0, read);
            }
            return bos.toByteArray();
        } finally {
            if (is != null) is.close();
            conn.disconnect();
        }
    }

    // ── MULTIPART FILE UPLOAD ─────────────────────────────
    public JSONObject uploadFiles(String endpoint, String[] fieldNames, File[] files) throws Exception {
        String boundary = "----UnidarBoundary" + System.currentTimeMillis();
        HttpURLConnection conn = openConnection(endpoint, "POST");
        conn.setDoOutput(true);
        conn.setRequestProperty("Content-Type", "multipart/form-data; boundary=" + boundary);

        try (DataOutputStream dos = new DataOutputStream(conn.getOutputStream())) {
            for (int i = 0; i < files.length; i++) {
                File file = files[i];
                String fieldName = fieldNames[i];
                String mimeType = getMimeType(file.getName());

                dos.writeBytes("--" + boundary + "\r\n");
                dos.writeBytes("Content-Disposition: form-data; name=\"" + fieldName +
                               "\"; filename=\"" + file.getName() + "\"\r\n");
                dos.writeBytes("Content-Type: " + mimeType + "\r\n\r\n");
                dos.write(Files.readAllBytes(file.toPath()));
                dos.writeBytes("\r\n");
            }
            dos.writeBytes("--" + boundary + "--\r\n");
        }
        return readResponse(conn);
    }

    // ── Shared helpers ────────────────────────────────────
    private HttpURLConnection openConnection(String endpoint, String method) throws Exception {
        URL url = java.net.URI.create(BASE_URL + endpoint).toURL();
        HttpURLConnection conn = (HttpURLConnection) url.openConnection();
        conn.setRequestMethod(method);
        conn.setConnectTimeout(20_000);
        conn.setReadTimeout(30_000);
        conn.setRequestProperty("Accept", "application/json");
        // Send JWT or PHP Session as cookie
        if (authToken != null && !authToken.isEmpty()) {
            // PHP uses PHPSESSID, Next.js uses unidar_token
            // We'll send both to be safe, or just the one we have.
            // If it's a long JWT, it's likely unidar_token. If it's short, it's likely PHPSESSID.
            if (authToken.length() < 50) {
                conn.setRequestProperty("Cookie", "PHPSESSID=" + authToken);
            } else {
                conn.setRequestProperty("Cookie", "unidar_token=" + authToken);
            }
        }
        return conn;
    }

    private JSONObject readResponse(HttpURLConnection conn) throws Exception {
        int status = conn.getResponseCode();

        // ── Auto-capture unidar_token from Set-Cookie (e.g. login response) ──
        // This means ANY call that gets a Set-Cookie back will store the token,
        // so LoginController using post() works without any extra code.
        try {
            Map<String, List<String>> headers = conn.getHeaderFields();
            List<String> setCookies = headers.get("Set-Cookie");
            if (setCookies != null) {
                for (String cookieLine : setCookies) {
                    for (String part : cookieLine.split(";")) {
                        String trimmed = part.trim();
                        if (trimmed.startsWith("unidar_token=")) {
                            String val = trimmed.substring("unidar_token=".length()).trim();
                            if (!val.isEmpty() && !val.equals("\"\"")) {
                                authToken = val;
                                System.out.println("[ApiClient] unidar_token captured.");
                            }
                            break;
                        } else if (trimmed.startsWith("PHPSESSID=")) {
                            String val = trimmed.substring("PHPSESSID=".length()).trim();
                            if (!val.isEmpty()) {
                                authToken = val;
                                System.out.println("[ApiClient] PHPSESSID captured.");
                            }
                            break;
                        }
                    }
                }
            }
        } catch (Exception ignored) {}

        InputStream is = (status < 400) ? conn.getInputStream() : conn.getErrorStream();
        if (is == null) {
            System.err.println("[ApiClient] Error: Server returned " + status + " but no error stream.");
            return new JSONObject().put("status", status);
        }

        BufferedReader br = new BufferedReader(new InputStreamReader(is, StandardCharsets.UTF_8));
        StringBuilder sb = new StringBuilder();
        String line;
        while ((line = br.readLine()) != null) sb.append(line);

        String body = sb.toString().trim();
        if (status >= 400) {
            System.err.println("[ApiClient] Error response (" + status + "): " + body);
        }
        if (body.isEmpty()) return new JSONObject().put("status", status);

        // JSON array at root — wrap so callers always get a JSONObject
        if (body.startsWith("[")) {
            try {
                JSONArray arr = new JSONArray(body);
                return new JSONObject()
                        .put("listings",       arr)
                        .put("data",           arr)
                        .put("users",          arr)
                        .put("conversations",  arr)
                        .put("verifications",  arr)
                        .put("status",         status);
            } catch (Exception ignored) {}
        }

        // Normal JSON object
        try {
            return new JSONObject(body);
        } catch (Exception e) {
            System.err.println("[ApiClient] Non-JSON response (" + status + "): " +
                               body.substring(0, Math.min(200, body.length())));
            return new JSONObject().put("status", status)
                                   .put("error", "Server returned non-JSON response");
        }
    }

    private String getMimeType(String fileName) {
        String lower = fileName.toLowerCase();
        if (lower.endsWith(".pdf"))  return "application/pdf";
        if (lower.endsWith(".png"))  return "image/png";
        if (lower.endsWith(".jpg") || lower.endsWith(".jpeg")) return "image/jpeg";
        return "application/octet-stream";
    }
}
