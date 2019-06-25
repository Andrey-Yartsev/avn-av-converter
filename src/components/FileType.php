<?php
/**
 * User: pel
 * Date: 12/10/2018
 */

namespace Converter\components;


class FileType
{
    /** @var FileType */
    private static $instance;
    
    protected $typeToExtensions = [
        'audio/adpcm'                      =>'adp',
        'audio/basic'                      =>'au',
        'audio/midi'                       =>'mid',
        'audio/mp4'                        =>'mp4a',
        'audio/mpeg'                       =>'mp3',
        'audio/mp3'                        =>'mp3',
        'audio/ogg'                        =>'ogg',
        'audio/s3m'                        =>'s3m',
        'audio/silk'                       =>'sil',
        'audio/vnd.dece.audio'             =>'uva',
        'audio/vnd.digital-winds'          =>'eol',
        'audio/vnd.dra'                    =>'dra',
        'audio/vnd.dts'                    =>'dts',
        'audio/vnd.dts.hd'                 =>'dtshd',
        'audio/vnd.lucent.voice'           =>'lvp',
        'audio/vnd.ms-playready.media.pya' =>'pya',
        'audio/vnd.nuera.ecelp4800'        =>'ecelp4800',
        'audio/vnd.nuera.ecelp7470'        =>'ecelp7470',
        'audio/vnd.nuera.ecelp9600'        =>'ecelp9600',
        'audio/vnd.rip'                    =>'rip',
        'audio/webm'                       =>'weba',
        'audio/x-aac'                      =>'aac',
        'audio/x-aiff'                     =>'aif',
        'audio/x-caf'                      =>'caf',
        'audio/x-flac'                     =>'flac',
        'audio/x-matroska'                 =>'mka',
        'audio/x-mpegurl'                  =>'m3u',
        'audio/x-ms-wax'                   =>'wax',
        'audio/x-ms-wma'                   =>'wma',
        'audio/x-pn-realaudio'             =>'ram',
        'audio/x-pn-realaudio-plugin'      =>'rmp',
        'audio/x-wav'                      =>'wav',
        'audio/xm'                         =>'xm',
        'image/bmp'                        =>'bmp',
        'image/cgm'                        =>'cgm',
        'image/g3fax'                      =>'g3',
        'image/gif'                        =>'gif',
        'image/ief'                        =>'ief',
        'image/jpeg'                       =>'jpeg',
        'image/ktx'                        =>'ktx',
        'image/png'                        =>'png',
        'image/prs.btif'                   =>'btif',
        'image/sgi'                        =>'sgi',
        'image/svg+xml'                    =>'svg',
        'image/tiff'                       =>'tiff',
        'image/vnd.adobe.photoshop'        =>'psd',
        'image/vnd.dece.graphic'           =>'uvi',
        'image/vnd.dvb.subtitle'           =>'sub',
        'image/vnd.djvu'                   =>'djvu',
        'image/vnd.dwg'                    =>'dwg',
        'image/vnd.dxf'                    =>'dxf',
        'image/vnd.fastbidsheet'           =>'fbs',
        'image/vnd.fpx'                    =>'fpx',
        'image/vnd.fst'                    =>'fst',
        'image/vnd.fujixerox.edmics-mmr'   =>'mmr',
        'image/vnd.fujixerox.edmics-rlc'   =>'rlc',
        'image/vnd.ms-modi'                =>'mdi',
        'image/vnd.ms-photo'               =>'wdp',
        'image/vnd.net-fpx'                =>'npx',
        'image/vnd.wap.wbmp'               =>'wbmp',
        'image/vnd.xiff'                   =>'xif',
        'image/webp'                       =>'webp',
        'image/x-3ds'                      =>'3ds',
        'image/x-cmu-raster'               =>'ras',
        'image/x-cmx'                      =>'cmx',
        'image/x-freehand'                 =>'fh',
        'image/x-icon'                     =>'ico',
        'image/x-mrsid-image'              =>'sid',
        'image/x-pcx'                      =>'pcx',
        'image/x-pict'                     =>'pic',
        'image/x-portable-anymap'          =>'pnm',
        'image/x-portable-bitmap'          =>'pbm',
        'image/x-portable-graymap'         =>'pgm',
        'image/x-portable-pixmap'          =>'ppm',
        'image/x-rgb'                      =>'rgb',
        'image/x-tga'                      =>'tga',
        'image/x-xbitmap'                  =>'xbm',
        'image/x-xpixmap'                  =>'xpm',
        'image/x-xwindowdump'              =>'xwd',
        'video/3gpp'                       =>'3gp',
        'video/3gpp2'                      =>'3g2',
        'video/h261'                       =>'h261',
        'video/h263'                       =>'h263',
        'video/h264'                       =>'h264',
        'video/jpeg'                       =>'jpgv',
        'video/jpm'                        =>'jpm',
        'video/mj2'                        =>'mj2',
        'video/mp4'                        =>'mp4',
        'video/mpeg'                       =>'mpeg',
        'video/ogg'                        =>'ogv',
        'video/quicktime'                  =>'mov',
        'video/vnd.dece.hd'                =>'uvh',
        'video/vnd.dece.mobile'            =>'uvm',
        'video/vnd.dece.pd'                =>'uvp',
        'video/vnd.dece.sd'                =>'uvs',
        'video/vnd.dece.video'             =>'uvv',
        'video/vnd.dvb.file'               =>'dvb',
        'video/vnd.fvt'                    =>'fvt',
        'video/vnd.mpegurl'                =>'m4u',
        'video/vnd.ms-playready.media.pyv' =>'pyv',
        'video/vnd.uvvu.mp4'               =>'uvu',
        'video/vnd.vivo'                   =>'viv',
        'video/webm'                       =>'webm',
        'video/x-f4v'                      =>'f4v',
        'video/x-fli'                      =>'fli',
        'video/x-flv'                      =>'flv',
        'video/x-m4v'                      =>'m4v',
        'video/x-matroska'                 =>'mkv',
        'video/x-mng'                      =>'mng',
        'video/x-ms-asf'                   =>'asf',
        'video/x-ms-vob'                   =>'vob',
        'video/x-ms-wm'                    =>'wm',
        'video/x-ms-wmv'                   =>'wmv',
        'video/x-ms-wmx'                   =>'wmx',
        'video/x-ms-wvx'                   =>'wvx',
        'video/x-msvideo'                  =>'avi',
        'video/x-sgi-movie'                =>'movie',
        'video/x-smv'                      =>'smv',
    ];
    
    /**
     * @return FileType
     */
    public static function getInstance()
    {
        if (self::$instance === null) {
            self::$instance = new FileType();
        }
        
        return self::$instance;
    }
    
    public function findExtensions($mimeType)
    {
        if (isset($this->typeToExtensions[$mimeType])) {
            return $this->typeToExtensions[$mimeType];
        }
        Logger::send('invalid_type', [
            'mimeType' => $mimeType
        ]);
        return null;
    }
    
    private function __construct()
    {
    }
    
    private function __clone()
    {
    }
    
    private function __wakeup()
    {
    }
}