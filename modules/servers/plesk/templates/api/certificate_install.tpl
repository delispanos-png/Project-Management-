<certificate>
    <install>
        <name><?php echo $certificateDomain; ?></name>
        <webspace><?php echo $domain; ?></webspace>
        <content>
            <csr><?php echo $csr; ?></csr>
            <pvt><?php echo $key; ?></pvt>
            <cert><?php echo $certificate; ?></cert>
        </content>
    </install>
</certificate>
