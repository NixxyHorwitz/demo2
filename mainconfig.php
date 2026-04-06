<?php

if (phpversion() < '8.0') die('<h1>This script requires PHP version 8.0 and above.</h1>');
date_default_timezone_set('Asia/Jakarta');
ini_set('memory_limit', '128M');

error_reporting(E_ALL);
// error_reporting(0);

/* Configurations - start */
$config['db'] = array(
  'host'      => 'localhost',
  'username'  => 'root',
  'password'  => '',
  'name'      => 'onwork'
);

$config['apk_name'] = ''; // nama file apk kamu

   
/* Core Files - start */
require __DIR__ . '/vendor/autoload.php';
require 'core/database.php';
require 'core/model.php';
require 'core/function.php';
/* Core Files - end */

$model = new Model();

/* Web & meta config (perbaikan base_url & HTTPS) */
$__scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
if (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') {
  $__scheme = 'https';
  $_SERVER['HTTPS'] = 'on';
  $_SERVER['REQUEST_SCHEME'] = 'https';
}
$__host = $_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? 'localhost';

$web = $model->db_query($db, "title, link_telegram, meta_keyword, meta_description, mt_web, min_depo, min_wd, withdraw_fee, withdraw_fee_percent, profit_mode", "settings")['rows'];
$config['web'] = array(
  'base_url'      => $__scheme . '://' . $__host . '/', // FIX: akurat di balik proxy/Cloudflare
  'title'         => $web['title'],
  'lte'         => $web['link_telegram'],
  'min_wd'         => $web['min_wd'],
  'min_depo'         => $web['min_depo'],
  'withdraw_fee'         => $web['withdraw_fee'],
  'profit_mode'         => $web['profit_mode'],
  'withdraw_fee_percent'         => $web['withdraw_fee_percent']
);
$config['meta'] = array(
  'description'   => $web['meta_description'], // Meta Description
  'keyword'       => $web['meta_keyword'] // Meta Keyword
);

$komisi_raw = $model->db_query($db, "commission_biasa, commission_promotor", "settings")['rows'];
$komisi = [
  [$komisi_raw['commission_biasa']],
  [$komisi_raw['commission_promotor']]
];

$config['hmac'] = array(
  'key' => 'B]cd2,zZAT-pbt$((dYxVnR,JF*Wp+%Gi65j{$n4v?j{ySpC?Mn?36E$#}LZ_]zYLi}]Q3ee;ua9w5nr$37x_.+4Stg[G:wnwc@?.2ggLZd4mQJ8ERk;[pQerEu{RdvqP];7t&8ciWufhn:jqM,EX?BdvvDHVBaj;(5:g@8PSJt:{FV9}irjdafJ[BFGCrJ.t8nvw]7%,[;cB9$RUg?/{$Fdw;gN6MuT66MM?BL{M_iCjWTMe=8f5J4v!B%aN;:Vgz-SqGQ6PA75=tnNZ,.LC6PV{Agy_zJyey&tCA)hDBvrd(5pUFZ[GN3G3_,jSq%ZV4q=RW#/aEzS2CdZ@yek?[[Ht7hAMb/uu3/Nwp+(fDpZdDLak@WX/;PHtf4b!7G+Cy3]dav%nX27AV;yY8?Lyc27)(CvBnZ:2%_D&rAb@f[jk7m)U(fS=5z]8C;[E{/)yt*cyk78DA/B=cPb!@L+YbQ,;yg]39*F%Lx/g(C-.?-$Q@=nd:-$T}(KJzw8:v6+Ty%?umP;t_Rc@k_qMjGHDXpMj8TAJdLK]DzfxG$S)G&H?]?e8_CTZ;P{YM=+c3CLNYg{BHr/R5&-Vcbu3t/b;Rb.EH-uL.Nact(pEdwZD[#3;f7,Ng-!;/Rp*v5#NQ7;+&)z:@wKb[Y{XvZ=i()Uv9qHmRKR4n#D!X{%WjSM6S%mhSB,y*qSNMt3j+bKn)d-SgQ&/Ha6@3DgBRXmEw+P]u6,TA3!vndt#/fFFA,72m&kXK)TRV3Y:RBcr{LKyW&V+H-wNxU/7$H/yRdJjExDZTX27GTE#e:H8xRA&Vi8t9=8VrMvGpabtFqb?zhbqp{;dzyKSirB_Vy!!N5Lz$,8Ey&*c5Mq,{WqH9L]6jM;24{n7Zy,NMi&t!J]M[@:B!hu&;HAn_A8mf.piSUT}*G7:cfCh3R}Eb8d$VfZR4{U?(#!@=5cNg[-=a(wDZ5_vqkPk9Z)ym%+HT#=25ZfVBff}$UgaVK?9{[$5EqA{@GUvn8(pAQ[cJnN[P#ZxH/7}Kx:ngc,4&y}wTHS$V&=3{k+(Wdd%z@$EF#9gLG/X/+Kin7}NR(zey4*J!v&M=ZH?N{kfb+$?tt*[Q6+CxXHVm*cpv:#yj6@U$]$AYp{rh4.WL!N?pmxezE2[]UPC%rR?Fm#kp]tLX-@PE9qNePRjp_;.3uGmBrMPNG;fRW3[Ki(raX/}dr(k#kXHt}$c-P6mpGEf-qA3Up!%e!F(k8(wXhTCPZ]wAN?kBm,3[E#KmL;%EM9(Tj=(Kc.?B?@*z#xrvRaD}z+J4{XzLTqJ+2ycxxuhMY/:+%G.Y+[C$(2uR/uFh,-w7TD?f=rRR?/VC@VC)Q7J*KufvWHnSfmZ/-iy$nF@=52L&bg%g?P7H(3{r=M}$V?SNAjL6hnPBc7awWk!{n)W((=jJ9}hH?rA8UVbZF.A[/)Z-eb;W$etnk{:=@{?S;br?krqfp2FJ@ea=:i@&$7++gH6[$mPd2;bVEFm:)QQAQ.By!g@7bv6j3wgixWz.3i]8MCK}t[$NmK?UbADa(_*6gUTAKpLmPf6BGq+Wp].?$qU}xStdDFUxB_cbMK4kUVdP#=w]W[CP+X2xRUHzDDVZ,EC(y3=GNxyJh.2qf.DuP8Hx[G/wv@AxB4v/V]Z_v*P]+E+4.2e@U]N,6{?6tkq#pXA,)W)AQ(/F/2cLGYNg2{(G=/RKQ45wN6;E,.fK5quK_KeKFKYY3XhNXK*H28zW(4?(&$A%B?#*u,4BB.FFBZR$,y:hpjgWm%e*z-K6W[BzpEYvm{-erGt(H;F,TipyhSPxS,RC{%{WA[+$=XHPD[SAP-LVN&L9j3mGj6d=57Xz&q&r%;bL$Hi]5k#bUrtSG,p.,)hX$9N,WD.V+%8!]Fdg;WKTpifwP.8pznr25n(xz=t:#P%Tv3*XM&Hr:-Zy2gC,?hgPSg{BQj5ut.]j[,FBcePrgzihJxz]z/F_x=WnD=Eirw)ujHYmf;gMN+wW:!'
);
$config['jwt'] = array(
  'secret' => 'RPyv*&rAJE29m:$i4F!XVNLE[6bf$Xg-;qSek:cAfk:L9pGmR,]q-Eihz(/i;-zZi)yYbb?jA@Zp9{t/6cjtAf+2)Ddht?;N,iFa@iaR!iPb%[@pAi,rdrD6@[[!7rwZCMZr5G}LBt!M@(R2_hNS)TyqjUi@DBKMr&?{]9HtxA_Mb{;v?bXn)V7#vbPmtD}&J;ivgCHX6CGR[.t,i9[8M9Vi=Rk[t=UH5.GkP2TEkwDE(}:t_#y2UtK]n6v@EZ&ifHg}+/zBKYq)-f{wKj*bAx5zFJTe?U&&HZ;m_t;]aJ/k)[{ad)-%CVX+!Q8fZ(Zf)zFXg*}dY/HxpzA=5vSKd5r%bTG,HcNV.Mt(;ev)+}@pg,PqhD6tWt+wSxewy@)r_;NN_njKvv7&VqA$g,,pDiNw/2Vet)kQEt&uC(9X2&EjJ4%ntzVuqNG7VcxZZ8{,K;GzH)N@5N[WN*LykqP_V_Yn9yCvEXZrJ[fMB}r}4jCLN7YheD9.8p8rAJHk-$}kB@Z%K[HRFFQWWdFkT?@}XyPvSTWi&DN.y%*wjfjS5V%ahU:vFaeEQcn{v.ueFFJ2,i*F!)#)_Zcw)D{eXmr??/UuU@-MMqU[SUgR(7DB;J8qN(G,brX7ktct4}P3=$Q$ND&T!N,xm!gPw9)e{;+TKuFSwY8;-eNa#,U}JJy5HW#X/P+pYPb)kG(V&$v+ewYY$8AWZ/nE4F47Z3=)rU(DayTP#$6etQRFgL]k&6gxh{Y@igHn_PmQ}{(m7/{USt;.YyQg2dBc#L7E9C?;h=ey]3A!?6_?25b-$RRXS)5S_-vr2d*m+fa(BJ_}rg{+pRq4[7rhC9r2uqtHCh2A(v:6SfaSGhMivatD-zWitdr!Kd3;;MjV},xM)?wHV[5*C$L#W?X_V}V]WfPE3hvV9m32NdBH*d?$W7h=jEc}&b]vvy4xR5[Q4}Ubhf$W-}B#,:i,/m3h:7S&x#,2[$d}TL_B.#F-)!VXL4;C#N![mea8w)JDS%6cPSSES6r@z%+Dg{cE.RD4r.B.TCjTEQTX/WinH6C2gCdgA$u-gncm+#hkSh%*t[q#Y3JM79#E:)hb?nrJ#/Y,Fb{]$[bC(gT!t=dekv*F8u2ypcc3.;Ee;Z.&m%{anS*K{S35R6EcgDWZ.rDCKwfaT+A?ug$%NNyCwuNb}6Dz=5KLYtCG9$7[Xwi4F/4(vrF{]K:c(NHzhQ}8a_,;;ixC5Qy!HMbbNJ;K2nbAdedkmPLMej5b3_%qv.y:Qi:,_vBkE+7{iM)V}ZpSJe8[twvTj?B=n8[Td&J6TFZF2xFcGJ8q2j6!XS=[n,EU]S#=MMb!!UUcKT8%Lk9}Hu(2_;CUP?AaQQBt%-:Z6y(;xSz4$%?PRt}y9]z;m9i=/n2bu/@xmf9!2jghGP.y7+W3rhG}}86r+jc[B].v7wJFS$te64)YXzALpF7QFZG?Xz%Kqp68hL_)=?vp))%dy3miAN*@7&3Nxf&P?aj3jeJ8C[[5ax%JJ,pS7/qmBxrt=!4M7pzr}?rJQgEJLyvYJ@RM!4wN-C+YY&z]@{}$y:Szv=6_$?P}%cyM!Ma{c6ZEeuViej)@;J-LjL3gart7P=kj]i}8c&Rem?B_Y}Pa]y+Vn)32T:$tzULRR}Jf?Wxp!)PiC_U}5in*8KXjxuH,wZm,KD8R[Ge#i:3@7BFR2@Nx%HyB}7_Yb@zyTWA-Zi}e[hP!7TwB9=Ug]n!9rnypX4B,$Y:7THD+TCuck(jH2e(M45g2uS+Rc$SDS$xQePFUuZb}K=f*P8#SgR;YvpvD-CBrP=%X9eC8{tpQe%]vig{kcU)$h$%,gUNF[$iN?9(524$Ka)w2BC88P_GSrfi3YAPZVSk=;X(XDegXVtpV9G#x5e6_MYuu)9[t%+DSKFq/=SiuC8JgrKv5b88H&t.2Vu*zu{u=Q9YEwK&Na(ADiupi+Ex,]-6a%$+8=.GeF[f.MLfN8W-.$v#zAFS7(@{mafk3hn'
);
/* Configurations - end */
if (session_status() === PHP_SESSION_NONE) {
    $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');
    $domain = $_SERVER['SERVER_NAME'] ?? $_SERVER['HTTP_HOST'] ?? 'localhost';

    // Hanya set cookie jika session belum aktif
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'domain'   => $domain,
        'secure'   => $secure,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);

    session_start();
} else {
    // Session sudah aktif, jangan panggil session_start lagi
    // Bisa optional: log info atau abaikan
}

// Helper base_url jika belum tersedia di core/function.php
if (!function_exists('base_url')) {
  function base_url(string $path = ''): string
  {
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    if (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') {
      $scheme = 'https';
    }
    $host   = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $root   = rtrim($scheme . '://' . $host . '/', '/') . '/';
    return $root . ltrim($path, '/');
  }
}

///////////////////////////////////////////////////////////////
// =====================  Maintenance  ====================== //
///////////////////////////////////////////////////////////////

require 'lib/csrf_token.php';
if ($web['mt_web'] == 1 && !str_contains($_SERVER['REQUEST_URI'], 'admin/') && !str_contains($_SERVER['REQUEST_URI'], 'api/')) {
  require 'lib/maintenance.php';
  exit();
}
if (!function_exists('mb_substr')) {
    function mb_substr($string, $start, $length = null) {
        return substr($string, $start, $length);
    }
}

if (!function_exists('mb_strlen')) {
    function mb_strlen($string) {
        return strlen($string);
    }
}
