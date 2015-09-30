<script type="text/html" id="table-listing-group">
	<tr class="tj-groups-heading groups-heading js-tj-groups-heading">
		<th colspan="6">
			<div class="listing-heading-inner-wrap">
				<div class="group-name">
					<h4><?php _e( 'Translation Batch sent on ', 'wpml-translation-management' ) ?><%= TJ.last_update
						%></h4>
					<%= TJ.batch_name ? '<p><?php _e( 'Batch Name: ', 'wpml-translation-management' ) ?> <strong>' +
							TJ.batch_name + '</strong></p>' : '' %>
				</div>
			</div>
			<div class="buttons">
				<div href="#" class="button-secondary group-action group-expand"><span
						class="dashicons dashicons-plus"></span>&nbsp;<?php echo __( 'Expand', 'sitepress' ); ?></div>
				<div href="#" class="button-secondary group-action group-collapse"><span
						class="dashicons dashicons-minus"></span>&nbsp;<?php echo __( 'Collapse', 'sitepress' ); ?>
				</div>
			</div>
			<div class="listing-heading-summary">
				<ul>
					<li class="js-group-info">
						<span id="group-displayed-jobs" class="value"><%=TJ.how_many%></span>
						<span id="group-out-of-text" style="display:<%=TJ.show_out_of%>;"><?php echo __( 'out of',
																										 'sitepress' ); ?></span>
						<span id="group-all-jobs" class="value" style="display:<%=TJ.show_out_of%>;"><%=TJ.how_many_overall%></span> <?php echo __( 'Jobs',
																																					'sitepress' ); ?>
						<br>

						<div>
							<a href="#" id="group-previous-jobs"
							   style="display:<%=TJ.show_previous%>;"><?php echo __( '&laquo; continue from the previous page',
																					 'sitepress' ); ?><br></a>
						</div>
						<div>
							<a href="#" id="group-remaining-jobs"
							   style="display:<%=TJ.show_remaining%>;"> <?php echo __( 'continue on the next page &raquo;',
																					   'sitepress' ); ?></a>
						</div>
					</li>
					<%
					if(TJ.statuses) {
					%>
					<li>
						<ul class="group-list group-statuses js-group-statuses">
							<%
							_.each(TJ.statuses, function (count, status) {
							var percentage = count / TJ.how_many_overall * 100;
							%>
							<li>
								<span class="value"><%= percentage.toFixed(2) + '% ' %></span><%= status %>
							</li>
							<% }); %>
						</ul>
					</li>
					<%
					} %>
					<%
					if(TJ.languages) {
					%>
					<li>
						<ul class="group-list group-languages">

							<%
							_.each(TJ.languages, function (language_items, language) {
							%>
							<li>
								<span class="value"><%= language_items.length %></span> <%= language %>
							</li>
							<%
							});
							%>
						</ul>
					</li>
					<%
					} %>
				</ul>
			</div>
		</th>
	</tr>
</script>

<script type="text/html" id="batch-name-link-template">
	<a target="_blank" href="<%=TJ.url%>"><%=TJ.name%></a>
</script>
