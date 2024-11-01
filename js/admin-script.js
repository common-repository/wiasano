document.addEventListener(
	'DOMContentLoaded',
	function () {
		if ( ! wiasanoState) {
			console.error( "wiasanoState not available" );
			return;
		}

		let table = document.getElementById( 'mapping-table' );
		document.getElementById( 'add-mapping' ).addEventListener(
			'click',
			function () {
				let newRow        = document.createElement( "tr" );
				const newRowIndex = table.getElementsByTagName( 'tbody' )[0].getElementsByTagName( 'tr' ).length;
				let html          = '<td style="padding-left:0px;"><select name="wiasano_options[wiasano_field_mapping][' + newRowIndex + '][wp]">';

				let length = wiasanoState.wordpressFields.length;
				for (let i = 0; i < length; i++) {
					const customField = wiasanoState.wordpressFields[i];
					html             += '<option value="' + customField + '">' + customField + '</option>';
				}

				html += '</select></td><td style="padding-left:0px;"><select name="wiasano_options[wiasano_field_mapping][' + newRowIndex + '][wiasano]">';

				for (let key in wiasanoState.wiasanoFields) {
					const label = wiasanoState.wiasanoFields[key];
					html       += '<option value="' + key + '">' + label + '</option>';
				}

				html += '</select></td><td style="padding-left:0px;"><button type="button" class="button remove-row">' + wiasanoState.btnRemoveLabel + '</button></td>';

				newRow.innerHTML = html;

				table.getElementsByTagName( "tbody" )[0].appendChild( newRow );

				newRow.querySelector( '.remove-row' ).addEventListener(
					'click',
					function () {
						this.closest( 'tr' ).remove();
					}
				);
			}
		);

		table.querySelectorAll( '.remove-row' ).forEach(
			function (button) {
				button.addEventListener(
					'click',
					function () {
						this.closest( 'tr' ).remove();
					}
				);
			}
		);
	}
);
