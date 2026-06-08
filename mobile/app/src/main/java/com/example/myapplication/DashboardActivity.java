package com.example.myapplication;

import android.content.Intent;
import android.content.SharedPreferences;
import android.os.Bundle;
import android.widget.ImageView;
import android.widget.TextView;
import android.widget.Toast;

import androidx.appcompat.app.AppCompatActivity;

import com.example.myapplication.network.ApiClient;
import com.example.myapplication.network.ApiService;
import com.google.gson.JsonObject;

import retrofit2.Call;
import retrofit2.Callback;
import retrofit2.Response;

public class DashboardActivity extends AppCompatActivity {

    private TextView tvWelcome, tvMonthlyTotal, tvInvoiceCount, tvProductCount, tvLowStock;
    private ImageView btnLogout;

    @Override
    protected void onCreate(Bundle savedInstanceState) {
        super.onCreate(savedInstanceState);
        setContentView(R.layout.activity_dashboard);

        tvWelcome = findViewById(R.id.tvWelcome);
        tvMonthlyTotal = findViewById(R.id.tvMonthlyTotal);
        tvInvoiceCount = findViewById(R.id.tvInvoiceCount);
        tvProductCount = findViewById(R.id.tvProductCount);
        tvLowStock = findViewById(R.id.tvLowStock);
        btnLogout = findViewById(R.id.btnLogout);

        SharedPreferences prefs = getSharedPreferences("factuflash_prefs", MODE_PRIVATE);
        String userName = prefs.getString("user_name", "Usuario");
        String token = prefs.getString("token", "");

        tvWelcome.setText("Hola, " + userName);

        btnLogout.setOnClickListener(v -> logout());

        loadDashboardStats(token);
    }

    private void loadDashboardStats(String token) {
        ApiService apiService = ApiClient.getClient().create(ApiService.class);
        Call<JsonObject> call = apiService.getDashboardStats("Bearer " + token);

        call.enqueue(new Callback<JsonObject>() {
            @Override
            public void onResponse(Call<JsonObject> call, Response<JsonObject> response) {
                if (response.isSuccessful() && response.body() != null) {
                    JsonObject data = response.body();
                    JsonObject stats = data.getAsJsonObject("stats");
                    
                    if (stats != null) {
                        double total = stats.get("monthly_total").getAsDouble();
                        int invoices = stats.get("invoice_count").getAsInt();
                        int products = stats.get("product_count").getAsInt();
                        int lowStock = stats.get("low_stock_count").getAsInt();

                        tvMonthlyTotal.setText(String.format("$ %.2f", total));
                        tvInvoiceCount.setText(String.valueOf(invoices));
                        tvProductCount.setText(String.valueOf(products));
                        tvLowStock.setText(lowStock + " productos");
                    }
                } else if (response.code() == 401) {
                    Toast.makeText(DashboardActivity.this, "Sesión expirada", Toast.LENGTH_SHORT).show();
                    logout();
                } else {
                    Toast.makeText(DashboardActivity.this, "Error al cargar datos", Toast.LENGTH_SHORT).show();
                }
            }

            @Override
            public void onFailure(Call<JsonObject> call, Throwable t) {
                Toast.makeText(DashboardActivity.this, "Error de red: " + t.getMessage(), Toast.LENGTH_SHORT).show();
            }
        });
    }

    private void logout() {
        SharedPreferences prefs = getSharedPreferences("factuflash_prefs", MODE_PRIVATE);
        prefs.edit().clear().apply();
        
        startActivity(new Intent(DashboardActivity.this, LoginActivity.class));
        finish();
    }
}
