rm -rf logs
mkdir logs
rm -rf RESULTADOS
mkdir RESULTADOS

php main.php aitana > logs/aitana.log 2>&1 &
php main.php alcolea > logs/alcolea.log 2>&1 &
php main.php alicante > logs/alicante.log 2>&1 &
php main.php aspontes > logs/aspontes.log 2>&1 &
php main.php asturiaswam > logs/asturiaswam.log 2>&1 &
php main.php asturiaswam-rx00 > logs/asturiaswam-rx00.log 2>&1 &
php main.php asturiaswam-rx01 > logs/asturiaswam-rx01.log 2>&1 &
php main.php asturiaswam-rx02 > logs/asturiaswam-rx02.log 2>&1 &
php main.php asturiaswam-rx04 > logs/asturiaswam-rx04.log 2>&1 &
wait

php main.php asturiaswam-rx05 > logs/asturiaswam-rx05.log 2>&1 &
php main.php auchlias > logs/auchlias.log 2>&1 &
php main.php barajas > logs/barajas.log 2>&1 &
php main.php barcelona > logs/barcelona.log 2>&1 &
php main.php barcelona-psr > logs/barcelona-psr.log 2>&1 &
php main.php begas > logs/begas.log 2>&1 &
php main.php begas-psr > logs/begas-psr.log 2>&1 &
php main.php biarritz > logs/biarritz.log 2>&1 &
php main.php canchoblanco > logs/canchoblanco.log 2>&1 &
wait

php main.php constantina > logs/constantina.log 2>&1 &
php main.php eljudio > logs/eljudio.log 2>&1 &
php main.php eljudio-psr > logs/eljudio-psr.log 2>&1 &
php main.php erillas > logs/erillas.log 2>&1 &
php main.php espineiras > logs/espineiras.log 2>&1 &
php main.php espineiras-psr > logs/espineiras-psr.log 2>&1 &
php main.php foia > logs/foia.log 2>&1 &
php main.php foia_sin > logs/foia_sin.log 2>&1 &
php main.php fuerteventura > logs/fuerteventura.log 2>&1 &
wait

php main.php gazules > logs/gazules.log 2>&1 &
php main.php girona > logs/girona.log 2>&1 &
php main.php grancanaria > logs/grancanaria.log 2>&1 &
php main.php grancanaria-psr > logs/grancanaria-psr.log 2>&1 &
php main.php inoges > logs/inoges.log 2>&1 &
php main.php lapalma > logs/lapalma.log 2>&1 &
php main.php malaga1 > logs/malaga1.log 2>&1 &
php main.php malaga2 > logs/malaga2.log 2>&1 &
php main.php malaga2-psr > logs/malaga2-psr.log 2>&1 &
wait

php main.php monflorite > logs/monflorite.log 2>&1 &
php main.php montejunto > logs/montejunto.log 2>&1 &
php main.php montejunto_sin > logs/montejunto_sin.log 2>&1 &
php main.php montpellier > logs/montpellier.log 2>&1 &
php main.php motril > logs/motril.log 2>&1 &
php main.php olesa > logs/olesa.log 2>&1 &
php main.php palmamallorca > logs/palmamallorca.log 2>&1 &
php main.php palmamallorca-psr > logs/palmamallorca-psr.log 2>&1 &
php main.php paracuellos1 > logs/paracuellos1.log 2>&1 &
wait

php main.php paracuellos1-psr > logs/paracuellos1-psr.log 2>&1 &
php main.php paracuellos2 > logs/paracuellos2.log 2>&1 &
php main.php paracuellos2-psr > logs/paracuellos2-psr.log 2>&1 &
php main.php penaschache > logs/penaschache.log 2>&1 &
php main.php penaschachemil > logs/penaschachemil.log 2>&1 &
php main.php portosanto > logs/portosanto.log 2>&1 &
php main.php portosanto_sin > logs/portosanto_sin.log 2>&1 &
php main.php pozonieves > logs/pozonieves.log 2>&1 &
php main.php randa > logs/randa.log 2>&1 &
wait

php main.php randa-psr > logs/randa-psr.log 2>&1 &
php main.php sierraespuna > logs/sierraespuna.log 2>&1 &
php main.php soller > logs/soller.log 2>&1 &
php main.php solorzano > logs/solorzano.log 2>&1 &
php main.php taborno > logs/taborno.log 2>&1 &
php main.php tenerifesur > logs/tenerifesur.log 2>&1 &
php main.php tenerifesur-psr > logs/tenerifesur-psr.log 2>&1 &
php main.php turrillas > logs/turrillas.log 2>&1 &
php main.php valdespina > logs/valdespina.log 2>&1 &
php main.php valencia > logs/valencia.log 2>&1 &
php main.php valladolid > logs/valladolid.log 2>&1 &
php main.php villatobas > logs/villatobas.log 2>&1 &
wait

# aitana alcolea alicante aspontes asturiaswam asturiaswam-rx00 asturiaswam-rx01 asturiaswam-rx02 asturiaswam-rx04 asturiaswam-rx05 auchlias barajas barcelona begas biarritz canchoblanco constantina eljudio erillas espineiras foia foia_sin fuerteventura gazules girona grancanaria inoges lapalma malaga1 malaga2 monflorite montejunto montejunto_sin montpellier motril olesa palmamallorca paracuellos1 paracuellos2 penaschache penaschachemil portosanto portosanto_sin pozonieves randa sierraespuna soller solorzano taborno tenerifesur turrillas valdespina valencia valladolid villatobas
