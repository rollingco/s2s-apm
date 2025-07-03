<?php
// 🟢 LeoGaming + Coinspaid sandbox тестовий запит

// LeoGaming sandbox API endpoint
$url = 'https://api.leogaming.com/payment';

// ✅ Дані для тестової транзакції
$postData = [
    'amount'          => '10.00',            // Сума транзакції (тестова)
    'currency'        => 'USD',              // Валюта
    'payment_method'  => 'coinspaid',        // Використовуємо Coinspaid
    'order_id'        => 'ORDER_' . time(),  // Унікальний ID замовлення
    'customer_email'  => 'test@example.com', // Тестовий email
];

// Ініціалізація cURL
$ch = curl_init($url);

// Налаштування cURL
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postData));

// Виконання запиту
$response = curl_exec($ch);

// Перевірка на помилки
if (curl_errno($ch)) {
    echo "❌ cURL error: " . curl_error($ch);
} else {
    echo "✅ Sandbox response:<br><pre>";
    echo htmlspecialchars($response);
    echo "</pre>";

    // Спроба отримати redirect URL з відповіді
    $responseData = json_decode($response, true);
    if (isset($responseData['redirect_url'])) {
        echo "<br>🔗 <a href='" . htmlspecialchars($responseData['redirect_url']) . "' target='_blank'>Перейти до Coinspaid для тесту</a>";
    } else {
        echo "<br>⚠️ Redirect URL не знайдено у відповіді.";
    }
}

// Закриття cURL
curl_close($ch);
?>
