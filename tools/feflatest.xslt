<?xml version="1.0" encoding="UTF-8"?>
<xsl:stylesheet version="1.0" xmlns:xsl="http://www.w3.org/1999/XSL/Transform">
	<xsl:output method="xml" version="1.0" indent="yes" />

	<xsl:template match="/updates/update[1]">
	<latestFEF>
		<xsl:apply-templates/>
	</latestFEF>
	</xsl:template>
	
	<xsl:template match="/updates/update[1]/version">
	<version><xsl:value-of select="./text()"/></version>
	</xsl:template>

	<xsl:template match="/updates/update[1]/downloads/downloadurl">
	<download><xsl:value-of select="./text()"/></download>
	</xsl:template>
	
	<xsl:template match="text()|@*">
	</xsl:template>
</xsl:stylesheet>
