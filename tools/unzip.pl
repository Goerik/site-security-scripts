#!/usr/bin/perl 

foreach $z (@ARGV) {
    $d = substr($z, 0, -4);
    $zt = `unzip -d $d $z`;
    @files = ($zt =~ /(?<=inflating: ).*?(?=\s*$)/mg);
    &decode_names(@files)
}

exit;

sub decode_names {
    foreach $i (@_) {
        $new_name = 
          `echo -n $i | iconv -f cp1252 -t cp850 | iconv -f cp866`;
        ($i eq $new_name) || rename($i, $new_name);
        print "\textracted: $new_name\n";
    }
}
