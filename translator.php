<?php
// translator.php - File ini berfungsi memuat script Google Translate Widget.
// Catatan: Google Translate API (Gratis Widget) menggunakan JavaScript dan akan 
// bekerja secara independen dari server PHP setelah di-load.

// --- Konfigurasi Widget ---
$pageLanguage = 'id'; // Bahasa asli website Anda
$includedLanguages = 'en,ja,zh-CN'; // Bahasa tujuan yang ditawarkan (Contoh: Inggris, Jepang, Mandarin)
$layout = 'SIMPLE'; // Gaya widget (SIMPLE atau HORIZONTAL)

// Tambahkan CSS khusus untuk mengatasi styling widget di dalam navbar
$custom_css = '
<style>
    /* Google Translate Widget Styling Fixes */
    #google_translate_element {
        line-height: 1.8; /* Menjaga vertikal alignment di navbar */
        padding: 0.5rem 0.5rem;
    }
    .goog-te-gadget-simple {
        background-color: transparent !important;
        border: none !important;
        font-size: 14px;
    }
    .goog-te-gadget-simple .goog-te-menu-value {
        color: white !important; /* Agar terlihat di navbar gelap */
        font-weight: bold;
    }
</style>
';
// --- END Konfigurasi Widget ---

// --- Script Inisialisasi Google Translate ---
$translator_scripts = '
<script type="text/javascript">
    function googleTranslateElementInit() {
        new google.translate.TranslateElement({
            pageLanguage: \'' . $pageLanguage . '\',
            includedLanguages: \'' . $includedLanguages . '\',
            layout: google.translate.TranslateElement.InlineLayout.' . $layout . '
        }, \'google_translate_element\');
    }
</script>
<script type="text/javascript" src="//translate.google.com/translate_a/element.js?cb=googleTranslateElementInit"></script>
';

// Output CSS dan Script
echo $custom_css;
echo $translator_scripts;

// Fungsi ini TIDAK mengembalikan nilai apapun, hanya mencetak (echo) 
// HTML dan JavaScript yang diperlukan.
// File ini HARUS dipanggil di bagian <head> atau footer halaman Anda.
// Dan Anda harus memiliki <div id="google_translate_element"></div> di tempat yang diinginkan.
?>