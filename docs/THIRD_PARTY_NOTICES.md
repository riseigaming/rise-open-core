# Selected third-party notices

Rise Open Core is distributed under Apache-2.0. Included upstream code and assets retain their own licenses and attribution. The Dice PHP adaptation preserves [vFair attribution](licenses/vfair-dice/README.md) and the unchanged [upstream LICENSE](licenses/vfair-dice/LICENSE). Package versions, source hashes and transformations are recorded in [vendor provenance](provenance/frontend-vendor.json).

| Component | Version/ref | Recorded licence | Included notice |
|---|---|---|---|
| jquery | 3.7.1 | MIT | public/vendor/jquery/LICENSE.txt |
| bootstrap | 5.3.8 | MIT | public/vendor/bootstrap/LICENSE |
| @popperjs/core (embedded in Bootstrap bundle) | 2.11.8 source comparison | MIT | [full MIT license](../public/vendor/bootstrap/POPPER-LICENSE.md) |
| @tabler/core | 1.0.0 | MIT | public/vendor/tabler/LICENSE |
| toastr | 2.1.4 | [{"url": "http://www.opensource.org/licenses/mit-license.php", "type": "MIT"}] | public/vendor/toastr/LICENSE |
| bootstrap-table | 1.27.3 | MIT | public/vendor/bootstrap-table/LICENSE |
| @fortawesome/fontawesome-free | 6.7.2 | (CC-BY-4.0 AND OFL-1.1 AND MIT) | public/vendor/fontawesome/LICENSE.txt |
| davicotico/jquery-menu-editor | 56a8afbdfa91ccd3bb473bcd017da786bd833bac | MIT | public/vendor/menu-editor/LICENSE |
| victor-valencia/bootstrap-iconpicker | 24cf38fe996aa4b3339d434e455eb40ff6aad476 | MIT | public/vendor/iconpicker/LICENSE |
| Popper.js (standalone legacy v1 script) | bundled script; exact patch version unverified | MIT | [full MIT license](licenses/popper-js/LICENSE.md), plus original file header |

Font Awesome has multiple licence components (icons/code/fonts); retain the upstream text rather than relabelling everything MIT or Apache-2.0. Composer transitive packages retain their upstream licences and are fetched from composer.lock; PHP vendor itself is not versioned.

Bootstrap bundles Popper code; its separate MIT notice is retained with the bundle. The standalone `public/dist/js/popper.min.js` retains its original 2017 copyright header; the accompanying full MIT text is reproduced from the upstream v1.16.1 license, without asserting that this is the script's packaged version. Both source and license evidence are recorded in the provenance manifest.
