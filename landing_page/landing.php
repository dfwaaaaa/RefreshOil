<?php
// Database connection parameters
$db_host = "localhost";
$db_user = "root";
$db_pass = "";
$db_name = "refresh_oil";

try {
    // Membuat koneksi PDO
    $conn = new PDO("mysql:host=$db_host;dbname=$db_name;charset=utf8", $db_user, $db_pass);
    // Mengatur mode error PDO ke Exception
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Mengambil 10 artikel terbaru
    $stmt = $conn->prepare("SELECT * FROM artikel ORDER BY created_at DESC LIMIT 10");
    $stmt->execute();
    $articles = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch(PDOException $e) {
    // Menangani error koneksi
    echo "Koneksi atau query database gagal: " . $e->getMessage();
    exit();
}
?>
<!DOCTYPE html> 
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>RefreshOil</title>
    <link href="./logo.png" rel="shortcut icon">
    <link href="./tiny-slider.css" rel="stylesheet">
    <!-- Tailwind CSS via CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600&display=swap" rel="stylesheet">
    <!-- Font Awesome JS -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.2.1/js/all.min.js" crossorigin="anonymous" referrerpolicy="no-referrer"></script>
    <!-- Tiny Slider CSS -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/tiny-slider/2.9.4/tiny-slider.css">
    <style>
        body {
            font-family: 'Poppins', sans-serif;
        }

        .mover {
            -webkit-animation: mover 1.5s infinite alternate;
            animation: mover 1.5s infinite alternate;
        }

        @-webkit-keyframes mover {
            0% { transform: translateY(0); }
            100% { transform: translateY(10px); }
        }

        @keyframes mover {
            0% { transform: translateY(0); }
            100% { transform: translateY(10px); }
        }

        html {
            scroll-behavior: smooth;
        }

        .navbar {
            transition: background-color 0.3s ease, box-shadow 0.3s ease;
            background-color: transparent; 
        }

        .navbar.scrolled {
            background-color: white; 
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1); 
        }

        .scrollbar-hide::-webkit-scrollbar {
            display: none;
        }

        .scrollbar-hide {
            -ms-overflow-style: none;
            scrollbar-width: none;
        }

        .slider-nav-button {
            position: absolute;
            top: 50%;
            transform: translateY(-50%);
            background-color: rgba(245, 158, 11, 0.8);
            color: white;
            border: none;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            cursor: pointer;
            z-index: 10;
            transition: background-color 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .slider-nav-button:hover {
            background-color: rgba(245, 158, 11, 1);
        }

        #prev-button {
            left: -25px;
        }

        #next-button {
            right: -25px;
        }

        @media (max-width: 768px) {
            #prev-button {
                left: -15px;
            }

            #next-button {
                right: -15px;
            }
        }

        /* Setiap kartu dengan w-[31%], inline-block, dan margin agar tidak terpotong dan memiliki jarak antar kartu */
        .news-item {
            display: inline-block; 
            vertical-align: top;
            width: 31%;
            margin-right: 1rem; /* mr-4 */
            transition: all 0.3s ease;
        }
        .news-item:last-child {
            margin-right: 0; /* last:mr-0 */
        }

        .news-item a.truncate-title {
            display: block;
            overflow: hidden;
            white-space: nowrap;
            text-overflow: ellipsis;
        }

        /* Modal Styles */
        .modal {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: rgba(0,0,0,0.6);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 1000;
        }

        .modal-content {
            background-color: white;
            padding: 1.5rem;
            border-radius: 0.5rem;
            max-width: 90%;
            max-height: 90%;
            overflow-y: auto;
            position: relative;
        }

        .modal-close {
            position: absolute;
            top: 0.5rem;
            right: 0.5rem;
            background: none;
            border: none;
            font-size: 1.5rem;
            cursor: pointer;
            color: #555;
        }

        @media (max-width: 768px) {
            .news-item {
                width: 48%;
                margin-right: 0.5rem;
            }
        }

        @media (max-width: 480px) {
            .news-item {
                width: 100%;
                margin-right: 0;
            }
        }
    </style>
</head>
<body class="font-[Poppins]">

    <!-- Navbar -->
    <header class="navbar fixed top-0 left-0 right-0 z-50 bg-transparent">
        <div class="container mx-auto px-4 py-4">
            <div class="grid grid-cols-1 md:grid-cols-12 items-center">
                <div class="md:col-span-3 flex justify-between md:justify-start">
                    <a href="landing.php">
                        <img src="./Banner2.png" class="h-8" alt="Refresh Oil Logo">
                    </a>
                </div>
                <div id="nav-menu" class="md:col-span-6 mt-4 md:mt-0 flex justify-center">
                    <nav>
                        <ul class="flex flex-col md:flex-row md:space-x-6 space-y-4 md:space-y-0">
                            <li><a href="#beranda" class="nav-link text-gray-800 hover:text-yellow-500 transition">Beranda</a></li>
                            <li><a href="#keunggulan" class="nav-link text-gray-800 hover:text-yellow-500 transition">Keunggulan</a></li>
                            <li><a href="#informasi" class="nav-link text-gray-800 hover:text-yellow-500 transition">Informasi</a></li>
                            <li><a href="#faq" class="nav-link text-gray-800 hover:text-yellow-500 transition">FAQ</a></li>
                            <li><a href="#kontak" class="nav-link text-gray-800 hover:text-yellow-500 transition">Kontak</a></li>
                        </ul>
                    </nav>
                </div>
                <div class="md:col-span-3 mt-4 md:mt-0 flex justify-center">
                    <button class="h-10 px-4 text-sm tracking-wide inline-flex items-center justify-center font-medium rounded-full bg-yellow-500 text-black hover:bg-yellow-600 transition">
                        <a href="/RefreshOil/halaman/login.php">Sign In</a>
                    </button>
                </div>
            </div>
        </div>
    </header>

    <section class="relative overflow-hidden pt-40 after:content-[''] after:absolute after:inset-0 after:mx-auto after:w-[56rem] after:h-[56rem] after:bg-gradient-to-tl after:to-yellow-400/40 after:from-yellow-300/40 after:blur-[200px] after:rounded-full after:-z-10 flex items-center justify-center min-h-screen" id="beranda">
        <div class="container relative z-20">
            <div class="grid grid-cols-1 text-center">
                <h1 class="text-gray-800 text-4xl font-thick lg:leading-normal leading-normal lg:text-[54px] text-center relative z-10" style="height: 120px;">
                    Platform Terbaik untuk
                    <span id="typing-text" class="text-gray-800 font-semibold absolute top-20 left-0 right-0 mb-6"></span>
                </h1>
                <div class="relative lg:mx-16 z-30 pt-7">
                    <img src="./minyak4.png" alt="Cooking Oil" class="mx-auto mover">
                </div>
            </div>
        </div>
    </section>

    <!-- Keunggulan -->
    <section class="relative md:py-24 py-16" id="keunggulan">
        <div class="container mx-auto relative px-4">
            <div class="text-center pb-6">
                <h6 class="text-yellow-500 uppercase text-sm font-bold tracking-wider mb-3">Keunggulan</h6>
                <h4 class="mb-6 md:text-3xl text-2xl font-bold">Kenapa Harus Kami?</h4>
                <p class="text-slate-400 max-w-xl mx-auto">
                    RefreshOil adalah platform terpercaya Anda dalam pendistribusian minyak jelantah yang efisien dan berkelanjutan.
                </p>
            </div>
            <div class="flex flex-col lg:flex-row items-center justify-center mt-4 gap-8">
                <div class="lg:w-1/3 w-full flex justify-center">
                    <img src="./minyak5.gif" class="mx-auto max-w-xs h-auto" alt="">
                </div>
                <div class="lg:w-2/3 w-full max-w-md">
                    <div class="space-y-6">
                        <div class="flex items-start">
                            <div class="flex-shrink-0 bg-yellow-500/10 hover:bg-yellow-500 text-yellow-500 hover:text-white rounded-2xl p-4 transition duration-500">
                                <i class="fas fa-shield text-2xl"></i>
                            </div>
                            <div class="ml-4">
                                <h4 class="text-lg font-semibold">Andal</h4>
                                <p class="text-slate-400 mt-2">Menjamin pengumpulan dan pengelolaan limbah minyak yang lancar dan dapat diandalkan.</p>
                            </div>
                        </div>
                        <div class="flex items-start">
                            <div class="flex-shrink-0 bg-yellow-500/10 hover:bg-yellow-500 text-yellow-500 hover:text-white rounded-2xl p-4 transition duration-500">
                                <i class="fas fa-truck text-2xl"></i>
                            </div>
                            <div class="ml-4">
                                <h4 class="text-lg font-semibold">Cepat</h4>
                                <p class="text-slate-400 mt-2">Menyediakan layanan penjemputan limbah minyak yang cepat tanpa penundaan.</p>
                            </div>
                        </div>
                        <div class="flex items-start">
                            <div class="flex-shrink-0 bg-yellow-500/10 hover:bg-yellow-500 text-yellow-500 hover:text-white rounded-2xl p-4 transition duration-500">
                                <i class="fas fa-sliders text-2xl"></i>
                            </div>
                            <div class="ml-4">
                                <h4 class="text-lg font-semibold">Efisien</h4>
                                <p class="text-slate-400 mt-2">Mengoptimalkan proses pengelolaan limbah minyak dari penjemputan hingga pengolahan untuk efisiensi maksimal.</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
    <!--end section-->

    <!-- Berita -->
    <section class="py-10 bg-yellow-50 md:py-24 py-16" id="informasi">
        <div class="container mx-auto px-4 text-center">
            <header class="mb-8">
                <h6 class="text-yellow-600 uppercase text-sm font-bold tracking-wider mb-3">Informasi</h6>
                <h2 class="text-3xl md:text-4xl font-bold tracking-tight text-black mb-4">Informasi Terbaru</h2>
                <p class="text-gray-700 text-base md:text-lg leading-relaxed max-w-2xl mx-auto">
                    Dapatkan informasi terkini dan tren.
                </p>
            </header>

            <div class="relative mx-auto" style="max-width: 1200px;">
                <div class="tiny-3-item">
                    <?php if(count($articles) > 0): ?>
                        <?php foreach($articles as $article): ?>
                            <div class="news-item bg-gray-100 backdrop-blur-2xl backdrop-brightness-150 rounded-lg w-full relative">
                                <span class="focus:outline-none text-[12px] bg-gray-900/80 text-slate-200 rounded-lg font-medium py-1 px-2 absolute top-1 left-1">
                                    <?php echo date("d M Y", strtotime($article['tgl_publish'])); ?>
                                </span>
                                <img src="../uploads/<?php echo htmlspecialchars($article['gambar_artikel']); ?>" alt="<?php echo htmlspecialchars($article['judul']); ?>" class="w-full h-48 object-cover rounded-lg">
                                <div class="flex-auto p-6 text-left">
                                    <a href="#" class="block text-[20px] font-thick tracking-tight text-black hover:text-gray-500 transition-colors truncate-title truncate" data-news-id="<?php echo $article['id_artikel']; ?>">
                                        <?php echo htmlspecialchars($article['judul']); ?>
                                    </a>
                                    <div class="block mt-3">
                                        <a href="#" class="inline-block text-blue-600 hover:text-gray-500 font-semibold transition-colors" data-news-id="<?php echo $article['id_artikel']; ?>">
                                            Selengkapnya
                                            <i class="fa fa-arrow-right ml-1"></i>
                                        </a>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p class="text-center text-gray-500">Tidak ada artikel terbaru.</p>
                    <?php endif; ?>
                </div>

                <button id="prev-button" class="slider-nav-button" aria-label="Previous Slide">
                    <i class="fas fa-chevron-left"></i>
                </button>
                <button id="next-button" class="slider-nav-button" aria-label="Next Slide">
                    <i class="fas fa-chevron-right"></i>
                </button>
            </div>
        </div>
    </section>
    <!-- End Berita -->

    <!-- Modal -->
    <div id="news-modal" class="modal hidden">
        <div class="modal-content">
            <button class="modal-close" id="modal-close">&times;</button>
            <div id="modal-body">
                <!-- Isi konten dari isiBerita.php akan dimuat di sini -->
                <p class="text-center text-gray-500">Memuat...</p>
            </div>
        </div>
    </div>
    <!-- End Modal -->

    <!-- FAQs -->
    <section class="relative overflow-hidden md:py-24 py-16" id="faq">
        <div class="container mx-auto px-4">
            <div class="grid md:grid-cols-2 grid-cols-1 items-center justify-center gap-8">
                <div class="relative order-1 md:order-2 flex justify-center">
                    <div class="relative">
                        <img src="./faq2.gif" class="max-w-md w-full h-auto" alt="Contact Image">
                        <div class="absolute top-24 md:right-14 -right-2 text-center">
                            <a href="#!" class="lightbox w-10 h-10 rounded-full shadow-md inline-flex items-center justify-center text-white bg-yellow-500">
                                <i class="fas fa-play text-xl"></i>
                            </a>
                        </div>
                    </div>
                    <div class="overflow-hidden absolute md:w-[500px] w-[400px] bg-gradient-to-tr to-yellow-500/20 via-yellow-500/70 from-yellow-500 bottom-1/2 translate-y-1/2 md:right-0 right-1/2 md:translate-x-0 translate-x-1/2 -z-10 shadow-md shadow-yellow-500/10 rounded-full"></div>
                </div>
                <div class="order-2 md:order-1">
                    <div class="text-center md:text-left">
                        <h6 class="text-yellow-600 uppercase text-sm font-bold tracking-wider mb-3">FAQ</h6>
                        <h4 class="mb-6 md:text-3xl text-2xl md:leading-normal leading-normal font-bold">Punya Pertanyaan? <br> Lihat Disini</h4>
                        <p class="text-slate-400 max-w-xl mx-auto md:mx-0">Mungkin pertanyaan anda telah terjawab dibawah. Apabila memiliki pertanyaan lebih lanjut, jangan segan untuk menghubungi kami!</p>

                        <div id="accordion-collapseone" class="mt-8">
                            <div class="relative shadow rounded-md overflow-hidden">
                                <h2 class="text-lg font-semibold" id="accordion-collapse-heading-1">
                                    <button type="button" class="flex justify-between items-center p-5 w-full font-medium text-start focus:outline-none" aria-expanded="false" aria-controls="accordion-collapse-body-1">
                                        <span>Apa itu platform RefreshOil?</span>
                                        <svg data-accordion-icon class="w-4 h-4 shrink-0 transition-transform duration-200" fill="currentColor">
                                            <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd"></path>
                                        </svg>
                                    </button>
                                </h2>
                                <div id="accordion-collapse-body-1" class="hidden" aria-labelledby="accordion-collapse-heading-1">
                                    <div class="p-5">
                                        <p class="text-slate-400">
                                        RefreshOil adalah platform pendistribusian yang dirancang untuk membantu proses pengumpulan limbah minyak dari masyarakat pada daerah tertentu. Terdapat juga fitur tukar poin, edukasi dan berita.
                                        </p>
                                    </div>
                                </div>
                            </div>

                            <div class="relative shadow rounded-md overflow-hidden mt-4">
                                <h2 class="text-lg font-semibold" id="accordion-collapse-heading-2">
                                    <button type="button" class="flex justify-between items-center p-5 w-full font-medium text-start focus:outline-none" aria-expanded="false" aria-controls="accordion-collapse-body-2">
                                        <span>Bagaimana cara melakukan penjemputan?</span>
                                        <svg data-accordion-icon class="w-4 h-4 shrink-0 transition-transform duration-200" fill="currentColor">
                                            <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd"></path>
                                        </svg>
                                    </button>
                                </h2>
                                <div id="accordion-collapse-body-2" class="hidden" aria-labelledby="accordion-collapse-heading-2">
                                    <div class="p-5">
                                        <p class="text-slate-400">
                                            Anda cukup mengisi formulir yang disediakan di halaman penjemputan. Isi dari formulir tersebut mencakup jumlah liter, alamat penjemputan, dan pemilihan waktu di jemput nya.
                                        </p>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="relative shadow rounded-md overflow-hidden mt-4">
                                <h2 class="text-lg font-semibold" id="accordion-collapse-heading-3">
                                    <button type="button" class="flex justify-between items-center p-5 w-full font-medium text-start focus:outline-none" aria-expanded="false" aria-controls="accordion-collapse-body-3">
                                        <span>Apa keuntungan yang saya dapat?</span>
                                        <svg data-accordion-icon class="w-4 h-4 shrink-0 transition-transform duration-200" fill="currentColor">
                                            <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd"></path>
                                        </svg>
                                    </button>
                                </h2>
                                <div id="accordion-collapse-body-3" class="hidden" aria-labelledby="accordion-collapse-heading-3">
                                    <div class="p-5">
                                        <p class="text-slate-400">
                                            Pastinya kami akan membeli minyak jelantah anda dengan harga yang bersaing.<br>
                                            Anda akan mendapatkan poin ketika melakukan penjemputan yang dimana poin tersebut dapat ditukar menjadi sembako loh!
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
    </section>

    <!-- Kontak -->
    <section class="relative md:py-24 py-16 bg-yellow-50" id="kontak">
        <div class="container mx-auto px-4">
            <div class="flex flex-col lg:flex-row items-center justify-center gap-12">
                <div class="lg:w-1/2 w-full flex justify-center">
                    <img src="./faq.gif" alt="Contact Image" class="max-w-md w-full h-auto">
                </div>
                <div class="lg:w-1/2 w-full">
                    <div class="bg-white rounded-md shadow p-6">
                        <h6 class="text-yellow-600 uppercase text-sm font-bold tracking-wider mb-3">Kontak Kami</h6>
                        <h4 class="mb-6 md:text-3xl text-2xl md:leading-normal leading-normal font-bold">Tinggalkan Sebuah Pesan!</h4>

                        <form method="post" name="myForm" id="myForm" onsubmit="return validateForm()">
                            <p class="mb-4 text-red-500" id="error-msg"></p>
                            <div id="simple-msg"></div>
                            <div class="grid grid-cols-1 gap-4">
                                <div>
                                    <label for="name" class="block font-medium">Nama Anda:</label>
                                    <input name="name" id="name" type="text" class="form-input mt-1 w-full py-2 px-3 bg-transparent text-slate-900 rounded outline-none border border-gray-100 focus:border-yellow-500 focus:ring-0" placeholder="Nama :">
                                </div>

                                <div>
                                    <label for="email" class="block font-medium">Email Anda:</label>
                                    <input name="email" id="email" type="email" class="form-input mt-1 w-full py-2 px-3 bg-transparent text-slate-900 rounded outline-none border border-gray-100 focus:border-yellow-500 focus:ring-0" placeholder="Email :">
                                </div>
                                <div>
                                    <label for="subject" class="block font-medium">Subjek Anda:</label>
                                    <input name="subject" id="subject" type="text" class="form-input mt-1 w-full py-2 px-3 bg-transparent text-slate-900 rounded outline-none border border-gray-100 focus:border-yellow-500 focus:ring-0" placeholder="Subjek :">
                                </div>
                                <div class="dropdown">
                                <label for="subject" class="block font-medium">Keperluan Anda:</label>
                                     <select name="keperluan" id="keperluan" class="form-select mt-1 w-full py-2 px-3 bg-transparent text-slate-900 rounded outline-none border border-gray-100 focus:border-yellow-500 focus:ring-0">
                                        <option>Bisnis</option>
                                        <option>Umum</option>
                                    </select>
                                </div>

                                <div>
                                    <label for="comments" class="block font-medium">Berikan Pesan Terbaik Anda:</label>
                                    <textarea name="comments" id="comments" rows="4" class="form-input mt-1 w-full py-2 px-3 bg-transparent text-slate-900 rounded outline-none border border-gray-100 focus:border-yellow-500 focus:ring-0" placeholder="Pesan :"></textarea>
                                </div>

                                <div>
                                    <button type="submit" id="submit" name="send" class="w-full py-2 px-5 tracking-wide inline-flex items-center justify-center font-medium rounded bg-yellow-500 text-white hover:bg-yellow-600 transition">Kirim</button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="py-6 border-t border-b border-gray-100">
        <div class="container mx-auto">
            <div class="flex justify-center items-center flex-wrap gap-6">
                <div class="py-4">
                    <img src="./jlantah.png" class="h-12" alt="Jlantah">
                </div>
                <div class="py-4">
                    <img src="./mallsampah.png" class="h-12" alt="Mall Sampah">
                </div>
                <div class="py-4">
                    <img src="./zerolim.png" class="h-12" alt="Zerolim">
                </div>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="py-8 bg-gray-100">
        <div class="container mx-auto">
            <div class="grid grid-cols-1 md:grid-cols-12 items-center">
                <div class="md:col-span-3 flex justify-center md:justify-start">
                    <a href="#" class="logo-footer">
                        <img src="./Banner2.png" class="h-8" alt="Refresh Oil Logo">
                    </a>
                </div>
                <div class="md:col-span-6 mt-8 md:mt-0 flex justify-center">
                    <div class="text-center">
                        <p class="text-gray-400">© <script>document.write(new Date().getFullYear())</script> Refresh Oil. All rights reserved.</p>
                    </div>
                </div>
                <div class="md:col-span-3 mt-8 md:mt-0">
                    <ul class="list-none flex justify-center md:justify-end space-x-4">
                        <li>
                            <a href="http://linkedin.com" target="_blank" class="inline-flex items-center justify-center tracking-wide text-base border border-gray-700 hover:border-yellow-500 rounded-md text-slate-300 hover:text-white hover:bg-yellow-500 p-2 transition duration-300">
                                <i class="fab fa-linkedin h-4 w-4" title="LinkedIn"></i>
                            </a>
                        </li>
                        <li>
                            <a href="https://www.instagram.com" target="_blank" class="inline-flex items-center justify-center tracking-wide text-base border border-gray-700 hover:border-yellow-500 rounded-md text-slate-300 hover:text-white hover:bg-yellow-500 p-2 transition duration-300">
                                <i class="fab fa-instagram h-4 w-4" title="Instagram"></i>
                            </a>
                        </li>
                        <li>
                            <a href="mailto:refreshoil.team@gmail.com" class="inline-flex items-center justify-center tracking-wide text-base border border-gray-700 hover:border-yellow-500 rounded-md text-slate-300 hover:text-white hover:bg-yellow-500 p-2 transition duration-300">
                                <i class="fas fa-envelope h-4 w-4" title="Email"></i>
                            </a>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </footer>

    <!-- Back to top -->
    <a href="javascript:void(0)" onclick="topFunction()" id="back-to-top" class="fixed hidden text-lg rounded-full z-50 bottom-5 right-5 h-10 w-10 text-center bg-yellow-500 text-white flex items-center justify-center cursor-pointer hover:bg-yellow-600 transition duration-300">
        <i class="fas fa-arrow-up"></i>
    </a>

    <!-- Tiny Slider JS -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/tiny-slider/2.9.4/min/tiny-slider.js"></script>

    <script>
        var slider;
        document.addEventListener('DOMContentLoaded', function() {
            initializeTypingEffect();
            initializeAccordion();
            initializeActiveNavbar();
            initializeCycleSlider();
            initializeSliderNavigation(); 
            initializeNewsModal(); // Initialize modal functionality
        });

        // Fungsi untuk gerakan slider maju hingga mencapai slide terakhir lalu langsung kembali ke awal (tanpa mundur per kartu)
        function initializeCycleSlider() {
            if (document.querySelector('.tiny-3-item')) {
                slider = tns({
                    container: '.tiny-3-item',
                    items: 3,
                    slideBy: 1,
                    autoplay: false,
                    controls: false,
                    nav: false,
                    mouseDrag: true,
                    loop: false,
                    speed: 1000,
                    responsive: {
                        0: {
                            items: 1
                        },
                        768: {
                            items: 2
                        },
                        1024: {
                            items: 3
                        }
                    }
                });

                var info = slider.getInfo();
                var maxIndex = info.slideCount - info.items;

                setInterval(function() {
                    info = slider.getInfo();
                    var index = info.index;

                    if (index < maxIndex) {
                        slider.goTo('next');
                    } else {
                        // Sudah di posisi terakhir, langsung lompat ke slide awal
                        slider.goTo(0);
                    }
                }, 3000);
            }
        }

        function initializeSliderNavigation() {
            const prevButton = document.getElementById('prev-button');
            const nextButton = document.getElementById('next-button');

            if (prevButton && nextButton && typeof slider !== 'undefined') {
                prevButton.addEventListener('click', function() {
                    slider.goTo('prev');
                });
                nextButton.addEventListener('click', function() {
                    slider.goTo('next');
                });
            }
        }

        function initializeTypingEffect() {
            const typingText = document.getElementById('typing-text');
            const texts = ['Distribusi Minyak Jelantah', 'Pengelolaan Minyak Jelantah', 'Kamu yang Mau Untung'];
            let textIndex = 0;
            let charIndex = 0;

            function type() {
                if (charIndex < texts[textIndex].length) {
                    typingText.textContent += texts[textIndex].charAt(charIndex);
                    charIndex++;
                    setTimeout(type, 100);
                } else {
                    setTimeout(erase, 2000);
                }
            }

            function erase() {
                if (charIndex > 0) {
                    typingText.textContent = texts[textIndex].substring(0, charIndex - 1);
                    charIndex--;
                    setTimeout(erase, 50);
                } else {
                    textIndex++;
                    if (textIndex >= texts.length) textIndex = 0;
                    setTimeout(type, 1000);
                }
            }

            setTimeout(type, 2000);
        }

        function initializeAccordion() {
            const accordionButtons = document.querySelectorAll('#accordion-collapseone button');
            accordionButtons.forEach(button => {
                button.addEventListener('click', () => {
                    const targetId = button.getAttribute('aria-controls');
                    const target = document.getElementById(targetId);
                    const isExpanded = button.getAttribute('aria-expanded') === 'true';

                    accordionButtons.forEach(btn => {
                        btn.setAttribute('aria-expanded', 'false');
                        const btnTargetId = btn.getAttribute('aria-controls');
                        const btnTarget = document.getElementById(btnTargetId);
                        btnTarget.classList.add('hidden');
                        btn.querySelector('svg').classList.remove('rotate-180');
                    });

                    if (!isExpanded) {
                        button.setAttribute('aria-expanded', 'true');
                        target.classList.remove('hidden');
                        button.querySelector('svg').classList.add('rotate-180');
                    }
                });
            });
        }

        function initializeActiveNavbar() {
            const navbar = document.querySelector('.navbar');
            const navLinks = document.querySelectorAll('nav ul li a');
            const sections = document.querySelectorAll('section[id]');

            window.addEventListener('scroll', function() {
                if (window.scrollY > 0) {
                    navbar.classList.add('scrolled');
                } else {
                    navbar.classList.remove('scrolled');
                }

                const backToTopButton = document.getElementById('back-to-top');
                if (window.scrollY > 300) {
                    backToTopButton.classList.remove('hidden');
                } else {
                    backToTopButton.classList.add('hidden');
                }
            });

            const observerOptions = {
                root: null,
                rootMargin: '0px',
                threshold: 0.6
            };

            const observer = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if(entry.isIntersecting){
                        navLinks.forEach(link => {
                            link.classList.remove('text-yellow-500', 'font-semibold');
                            link.classList.add('text-gray-800');
                        });
                        const id = entry.target.getAttribute('id');
                        const activeLink = document.querySelector(`nav ul li a[href="#${id}"]`);
                        if(activeLink){
                            activeLink.classList.remove('text-gray-800');
                            activeLink.classList.add('text-yellow-500', 'font-semibold');
                        }
                    }
                });
            }, observerOptions);

            sections.forEach(section => {
                observer.observe(section);
            });
        }

        function topFunction() {
            window.scrollTo({
                top: 0,
                behavior: 'smooth'
            });
        }

        function validateForm() {
            const name = document.getElementById('name').value.trim();
            const email = document.getElementById('email').value.trim();
            const subject = document.getElementById('subject').value.trim();
            const comments = document.getElementById('comments').value.trim();
            const errorMsg = document.getElementById('error-msg');

            if (!name || !email || !subject || !comments) {
                errorMsg.textContent = 'Semua kolom harus diisi!';
                return false;
            }

            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if (!emailRegex.test(email)) {
                errorMsg.textContent = 'Email tidak valid!';
                return false;
            }

            errorMsg.textContent = '';
            alert('Pesan Anda telah dikirim!');
            return false; 
        }

        // Modal Functionality
        function initializeNewsModal() {
            const modal = document.getElementById('news-modal');
            const modalClose = document.getElementById('modal-close');
            const modalBody = document.getElementById('modal-body');

            // Function to open modal with content
            function openModal(newsId) {
                modal.classList.remove('hidden');
                modalBody.innerHTML = '<p class="text-center text-gray-500">Memuat...</p>';

                // Fetch isiBerita.php dengan newsId sebagai parameter query
                fetch(`isiBerita.php?id=${newsId}`)
                    .then(response => {
                        if (!response.ok) {
                            throw new Error('Network response was not ok');
                        }
                        return response.text();
                    })
                    .then(data => {
                        modalBody.innerHTML = data;
                    })
                    .catch(error => {
                        modalBody.innerHTML = `<p class="text-center text-red-500">Terjadi kesalahan saat memuat konten.</p>`;
                        console.error('There has been a problem with your fetch operation:', error);
                    });
            }

            // Attach event listeners to all news links
            const newsLinks = document.querySelectorAll('.news-item a[data-news-id]');
            newsLinks.forEach(link => {
                link.addEventListener('click', function(e) {
                    e.preventDefault();
                    const newsId = this.getAttribute('data-news-id');
                    openModal(newsId);
                });
            });

            // Close modal when clicking the close button
            modalClose.addEventListener('click', function() {
                modal.classList.add('hidden');
                modalBody.innerHTML = '';
            });

            // Close modal when clicking outside the modal content
            window.addEventListener('click', function(e) {
                if (e.target == modal) {
                    modal.classList.add('hidden');
                    modalBody.innerHTML = '';
                }
            });
        }
    </script>
</body>
</html>