<?php
declare(strict_types=1);

require_once __DIR__ . '/../repositories/HomeRepository.php';

/**
 * HomeService - QOOQZ Platform
 * الطبقة الوسيطة التي تجمع البيانات من الـ Repository وتجهزها للـ Controller
 */
class HomeService
{
    private HomeRepository $repo;
    private string $lang;

    public function __construct(mysqli $conn)
    {
        // جلب اللغة من الجلسة أو المتصفح، وافتراض الانجليزية كخيار احتياطي
        $this->lang = $_SESSION['lang'] ?? 'en';
        $this->repo = new HomeRepository($conn, $this->lang);
    }

    /**
     * تجميع كافة بيانات الصفحة الرئيسية في مصفوفة واحدة منظمة
     */
    public function getHomeData(): array
    {
        return [
            // قسم الواجهة: الألوان، الخطوط، والتنسيقات
            'ui' => [
                'theme'   => $this->repo->getActiveTheme(),
                'colors'  => $this->repo->getColors(),
                'fonts'   => $this->repo->getFonts(),
                'buttons' => $this->repo->getButtons(),
                'cards'   => $this->repo->getCards(),
                'design'  => $this->repo->getDesignSettings(), // مضاف لدعم الـ RTL والـ Layout
            ],
            
            // قسم البيانات: المحتوى الفعلي المعروض
            'data' => [
                'sections' => $this->repo->getSections(), // ترتيب ظهور الأقسام
                'banners'  => $this->repo->getBanners(),  // الصور المتحركة

                'products' => [
                    'featured' => $this->repo->getFeaturedProducts(8), // المنتجات المميزة
                    'new'      => $this->repo->getNewProducts(8),      // أحدث المنتجات
                    'hot'      => $this->repo->getHotProducts(),       // العروض (فارغة حالياً)
                ],

                'vendors' => [
                    'featured' => $this->repo->getFeaturedVendors(6), // التجار المعتمدين (المميزين)
                ]
            ]
        ];
    }

    /**
     * وظيفة إضافية لجلب كافة التجار إذا طلب الـ Controller ذلك (لصفحة المتاجر)
     */
    public function getAllVendorsData(): array
    {
        return [
            'vendors' => $this->repo->getAllPublicVendors()
        ];
    }
}