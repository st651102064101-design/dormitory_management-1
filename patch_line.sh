#!/bin/bash
sed -i '' '/\/\* LINE OA icons: keep subtle motion in settings rows \*\//,/^}$/d' Reports/settings/apple-settings.css
sed -i '' '/@keyframes lineOABreathe/,/^}$/d' Reports/settings/apple-settings.css
sed -i '' '/@keyframes lineOAStrokePulse/,/^}$/d' Reports/settings/apple-settings.css
sed -i '' '/\.apple-row-icon\.line-oa-icon \.icon-animated/,/^}$/d' Reports/settings/apple-settings.css
