Add-Type -AssemblyName System.Drawing

$root = Split-Path -Parent $PSScriptRoot
$assets = Join-Path $root "assets"
$outDir = Join-Path $root "social\instagram"
New-Item -ItemType Directory -Force -Path $outDir | Out-Null

$brandGreen = [System.Drawing.Color]::FromArgb(22, 73, 59)
$gold = [System.Drawing.Color]::FromArgb(200, 147, 63)
$cream = [System.Drawing.Color]::FromArgb(247, 244, 235)
$white = [System.Drawing.Color]::FromArgb(255, 255, 255, 255)
$shadow = [System.Drawing.Color]::FromArgb(145, 0, 0, 0)
$panel = [System.Drawing.Color]::FromArgb(210, 247, 244, 235)

function Get-Font($name, $size, $style) {
    try {
        return New-Object System.Drawing.Font($name, $size, $style, [System.Drawing.GraphicsUnit]::Pixel)
    } catch {
        return New-Object System.Drawing.Font("Arial", $size, $style, [System.Drawing.GraphicsUnit]::Pixel)
    }
}

function Draw-CoverImage($graphics, $imagePath, $width, $height) {
    $image = [System.Drawing.Image]::FromFile($imagePath)
    $scale = [Math]::Max($width / $image.Width, $height / $image.Height)
    $drawWidth = [int]($image.Width * $scale)
    $drawHeight = [int]($image.Height * $scale)
    $x = [int](($width - $drawWidth) / 2)
    $y = [int](($height - $drawHeight) / 2)
    $graphics.DrawImage($image, $x, $y, $drawWidth, $drawHeight)
    $image.Dispose()
}

function Draw-Gradient($graphics, $width, $height) {
    $rect = New-Object System.Drawing.Rectangle(0, 0, $width, $height)
    $brush = New-Object System.Drawing.Drawing2D.LinearGradientBrush(
        $rect,
        [System.Drawing.Color]::FromArgb(210, 4, 20, 15),
        [System.Drawing.Color]::FromArgb(30, 4, 20, 15),
        [System.Drawing.Drawing2D.LinearGradientMode]::Vertical
    )
    $graphics.FillRectangle($brush, $rect)
    $brush.Dispose()
}

function Draw-WrappedText($graphics, $text, $font, $brush, $x, $y, $width, $lineHeight) {
    $words = $text -split "\s+"
    $line = ""
    foreach ($word in $words) {
        $candidate = if ($line.Length -eq 0) { $word } else { "$line $word" }
        $size = $graphics.MeasureString($candidate, $font)
        if ($size.Width -le $width -or $line.Length -eq 0) {
            $line = $candidate
        } else {
            $graphics.DrawString($line, $font, $brush, $x, $y)
            $y += $lineHeight
            $line = $word
        }
    }
    if ($line.Length -gt 0) {
        $graphics.DrawString($line, $font, $brush, $x, $y)
        $y += $lineHeight
    }
    return $y
}

function Draw-Logo($graphics, $width, $y) {
    $logoPath = Join-Path $assets "logo-veredas-site.png"
    $logo = [System.Drawing.Image]::FromFile($logoPath)
    $logoWidth = [int]($width * 0.17)
    $logoHeight = [int]($logo.Height * ($logoWidth / $logo.Width))
    $graphics.DrawImage($logo, 64, $y, $logoWidth, $logoHeight)
    $logo.Dispose()
}

function Save-Jpeg($bitmap, $path) {
    $codec = [System.Drawing.Imaging.ImageCodecInfo]::GetImageEncoders() | Where-Object { $_.MimeType -eq "image/jpeg" }
    $params = New-Object System.Drawing.Imaging.EncoderParameters(1)
    $params.Param[0] = New-Object System.Drawing.Imaging.EncoderParameter([System.Drawing.Imaging.Encoder]::Quality, 92L)
    $bitmap.Save($path, $codec, $params)
    $params.Dispose()
}

function New-Post($name, $image, $headline, $subtitle, $tag, $size) {
    $width = $size[0]
    $height = $size[1]
    $bitmap = New-Object System.Drawing.Bitmap($width, $height)
    $graphics = [System.Drawing.Graphics]::FromImage($bitmap)
    $graphics.SmoothingMode = [System.Drawing.Drawing2D.SmoothingMode]::AntiAlias
    $graphics.InterpolationMode = [System.Drawing.Drawing2D.InterpolationMode]::HighQualityBicubic
    $graphics.TextRenderingHint = [System.Drawing.Text.TextRenderingHint]::AntiAliasGridFit

    Draw-CoverImage $graphics (Join-Path $assets $image) $width $height
    Draw-Gradient $graphics $width $height

    $padding = if ($height -gt $width) { 78 } else { 64 }
    Draw-Logo $graphics $width $padding

    $headlineSize = 74
    $subtitleSize = 34
    $headlineLineHeight = 82
    $subtitleLineHeight = 44
    if ($height -gt $width) {
        $headlineSize = 92
        $subtitleSize = 42
        $headlineLineHeight = 100
        $subtitleLineHeight = 52
    }

    $tagFont = Get-Font "Montserrat" 28 ([System.Drawing.FontStyle]::Bold)
    $headlineFont = Get-Font "Georgia" $headlineSize ([System.Drawing.FontStyle]::Bold)
    $subtitleFont = Get-Font "Arial" $subtitleSize ([System.Drawing.FontStyle]::Regular)
    $smallFont = Get-Font "Arial" 28 ([System.Drawing.FontStyle]::Bold)

    $whiteBrush = New-Object System.Drawing.SolidBrush($white)
    $goldBrush = New-Object System.Drawing.SolidBrush($gold)
    $greenBrush = New-Object System.Drawing.SolidBrush($brandGreen)
    $shadowBrush = New-Object System.Drawing.SolidBrush($shadow)
    $panelBrush = New-Object System.Drawing.SolidBrush($panel)

    $textTop = if ($height -gt $width) { [int]($height * 0.51) } else { [int]($height * 0.43) }
    $textWidth = $width - ($padding * 2)

    $graphics.DrawString($tag.ToUpperInvariant(), $tagFont, $goldBrush, $padding, $textTop)
    $headlineY = $textTop + 44
    [void](Draw-WrappedText $graphics $headline $headlineFont $shadowBrush ($padding + 3) ($headlineY + 3) $textWidth $headlineLineHeight)
    $afterHeadline = Draw-WrappedText $graphics $headline $headlineFont $whiteBrush $padding $headlineY $textWidth $headlineLineHeight
    $afterSubtitle = Draw-WrappedText $graphics $subtitle $subtitleFont $whiteBrush $padding ($afterHeadline + 22) $textWidth $subtitleLineHeight

    $ctaText = "Fale com a equipe comercial"
    $ctaWidth = [int]($graphics.MeasureString($ctaText, $smallFont).Width + 56)
    $ctaHeight = 68
    $ctaY = [Math]::Min($height - $padding - $ctaHeight, $afterSubtitle + 42)
    $ctaRect = New-Object System.Drawing.Rectangle($padding, $ctaY, $ctaWidth, $ctaHeight)
    $graphics.FillRectangle($panelBrush, $ctaRect)
    $graphics.DrawString($ctaText, $smallFont, $greenBrush, ($padding + 28), ($ctaY + 18))

    $siteText = "veredasdoaraguaia.com.br"
    $graphics.DrawString($siteText, $smallFont, $whiteBrush, $padding, ($height - $padding - 34))

    $whiteBrush.Dispose()
    $goldBrush.Dispose()
    $greenBrush.Dispose()
    $shadowBrush.Dispose()
    $panelBrush.Dispose()
    $tagFont.Dispose()
    $headlineFont.Dispose()
    $subtitleFont.Dispose()
    $smallFont.Dispose()
    $graphics.Dispose()

    $suffix = if ($height -gt $width) { "story" } else { "feed" }
    $file = Join-Path $outDir "$name-$suffix.jpg"
    Save-Jpeg $bitmap $file
    $bitmap.Dispose()
}

$posts = @(
    @{
        Name = "01-lote-aruana"
        Image = "infra-implantacao-2.jpg"
        Tag = "Aruana, Goias"
        Headline = "Seu lote perto do Rio Araguaia"
        Subtitle = "Natureza, acesso facilitado e um empreendimento tomando forma para lazer e investimento."
    },
    @{
        Name = "02-lago-interno"
        Image = "infra-implantacao-1.jpg"
        Tag = "Veredas do Araguaia"
        Headline = "Lago interno e paisagem natural"
        Subtitle = "Um lugar para viver fins de semana com mais calma, água e verde ao redor."
    },
    @{
        Name = "03-infraestrutura"
        Image = "infra-implantacao-3.jpg"
        Tag = "Infraestrutura"
        Headline = "A implantação já começou"
        Subtitle = "Registro real das primeiras etapas de infraestrutura do condomínio de lotes."
    },
    @{
        Name = "04-rio-araguaia"
        Image = "rio-araguaia-margem.jpg"
        Tag = "Natureza"
        Headline = "O Araguaia como cenário"
        Subtitle = "Um empreendimento inspirado pela vegetação, pela água e pelo estilo de vida da região."
    },
    @{
        Name = "05-investimento"
        Image = "rio-araguaia-hero.jpg"
        Tag = "Investimento"
        Headline = "Aruanã segue valorizando"
        Subtitle = "Lotes em uma região turística, com vocação para lazer, descanso e patrimônio."
    },
    @{
        Name = "06-condicoes"
        Image = "rio-araguaia-galeria-1.jpg"
        Tag = "Condicoes especiais"
        Headline = "Receba mapa, valores e disponibilidade"
        Subtitle = "Preencha seus dados e fale com a equipe comercial do Veredas do Araguaia."
    }
)

foreach ($post in $posts) {
    New-Post $post.Name $post.Image $post.Headline $post.Subtitle $post.Tag @(1080, 1080)
    New-Post $post.Name $post.Image $post.Headline $post.Subtitle $post.Tag @(1080, 1920)
}

Write-Output "Posts criados em $outDir"
