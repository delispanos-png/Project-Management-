<!-- Slider Section -->
<div class="main-container slider">
    <div class="silder-container">
        <div class="carousel header-main-slider">
            <!-- 1 Slider -->
            <div class="carousel-cell overlay">
                <div class="slider-content w-55">
                    <h5 data-aos="fade-up" data-aos-duration="800">{$LANG.slider1Title}</h5>
                    <p data-aos="fade-up" data-aos-duration="1200">{$LANG.slider1content}</p>
                    <a class="btn btn-medium btn-prussian" href="cart.php?gid=4">{$LANG.seemore} <i class="ico-chevron-right w-icon"></i></a>
                </div>
                <div class="silder-video">
                    <div class="cover-wrapper">
                        <video class="cover-video" autoplay loop muted playsinline preload="metadata" width="1920" height="1080">
                            <source src="{$WEB_ROOT}/templates/{$template}/assets/videos/Cloudon-servers.mp4" type="video/mp4">
                        </video>
                    </div>
                </div>
            </div>
            <!-- 2 Slider -->
            {*<div class="carousel-cell overlay">
                <div class="special-note"><span class="badge">{$LANG.top}</span><span class="text">Search a domain of your choise from <b>$6.00/yr</b></span></div>
                <div class="slider-content w-55">
                    <h5 data-aos="fade-up" data-aos-duration="800">{$LANG.findyourdomain}</h5>
                    <p data-aos="fade-up" data-aos-duration="1200">{$LANG.cartdomainsconfigdesc}</p>
                    <form class="domains-search w-75" method="post" action="domainchecker.php">
                        <input type="text" class="inputdomainsearch" name="domain" placeholder="{$LANG.exampledomain}" autocapitalize="none" />
                        <span class="ds-content">
                            <button data-toggle="tooltip" data-placement="bottom" title="{$LANG.domainsregister}" type="submit" class="btn-search" value="{$LANG.domainsregister}"><i class="ico-globel f-16"></i></button>
                            <button data-toggle="tooltip" data-placement="bottom" title="{$LANG.domainstransfer}" type="submit" name="transfer" class="btn-transfer" value="{$LANG.domainstransfer}"><i class="ico-repeat f-16"></i></button>
                        </span>
                    </form>
                </div>
                <div class="carousel full-slider">
                    <img src="{$WEB_ROOT}/templates/{$template}/assets/img/sliders/Virtual-server-CloudOn.jpg" alt="Web Hosting" width="1920" height="1280"/>
                </div>
            </div>*}
            <!-- 3 Slider -->
            <div class="carousel-cell overlay">
                <div class="slider-content w-55">
                    <h5 data-aos="fade-up" data-aos-duration="800">{$LANG.slider2Title}</h5>
                    <p data-aos="fade-up" data-aos-duration="1200">{$LANG.slider2content}</p>
                    <a class="btn btn-medium btn-prussian" href="cart.php">{$LANG.seemore} <i class="ico-chevron-right w-icon"></i></a>
                </div>
                <div class="carousel full-slider">
                    <img src="{$WEB_ROOT}/templates/{$template}/assets/img/sliders/Virtual-server-CloudOn.jpg" alt="Web Hosting" width="1920" height="1280"/>
                </div>
            </div>
            <!-- 4 Slider -->
            <div class="carousel-cell overlay">
            <div class="special-note"><span class="badge">{$LANG.domainCheckerSalesGroup.hot}</span><span class="text">{$LANG.specialQuoteSlider}</span></div>
                <div class="slider-content w-55">
                    <h5>{$LANG.slider3Title}</h5>
                    <p>{$LANG.slider3content}</p>
                    <a class="btn btn-medium btn-prussian" href="submitticket.php">{$LANG.seemore} <i class="ico-chevron-right w-icon"></i></a>
                </div>
                <div class="carousel full-slider">
                    <img src="{$WEB_ROOT}/templates/{$template}/assets/img/sliders/Cloudon-Cloud-Storage.jpg" alt="Helpdesk" width="1920" height="1280"/>
                </div>
            </div>
        </div>
    </div>
</div>