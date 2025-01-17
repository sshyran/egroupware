/**
 * EGroupware eTemplate2 - Image widget
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package etemplate
 * @subpackage api
 * @link https://www.egroupware.org
 * @author Nathan Gray
 */


import {css, html, LitElement, SlotMixin} from "@lion/core";
import {Et2Widget} from "../Et2Widget/Et2Widget";
import {et2_IDetachedDOM} from "../et2_core_interfaces";

export class Et2Image extends Et2Widget(SlotMixin(LitElement)) implements et2_IDetachedDOM
{
	static get styles()
	{
		return [
			...super.styles,
			css`
            :host {
				display: inline-block;
            }
            ::slotted(img) {
            	max-height: 100%;
            	max-width: 100%;
            }
            `,
		];
	}

	static get properties()
	{
		return {
			...super.properties,

			/**
			 * The label of the image
			 * Actually not used as label, but we put it as title
			 * Added here as there's no Lion parent
			 */
			label: {
				type: String
			},

			/**
			 * Image
			 * Displayed image
			 */
			src: {type: String},

			/**
			 * Default image
			 * Image to use if src is not found
			 */
			default_src: {type: String},

			/**
			 * Link Target
			 * Link URL, empty if you don't wan't to display a link.
			 */
			href: {type: String},

			/**
			 * Link target
			 * Link target descriptor
			 */
			extra_link_target: {type: String},

			/**
			 * Popup
			 * widthxheight, if popup should be used, eg. 640x480
			 */
			extra_link_popup: {type: String},

			/**
			 * Expose view
			 * Clicking on an image with href value would popup an expose view, and will show image referenced by href.
			 */
			expose_view: {type: Boolean},
		}
	}

	get slots()
	{
		return {
			'': () =>
			{
				return this._imageTemplate();
			}
		}
	}

	constructor()
	{
		super();
		this.src = "";
		this.default_src = "";
		this.href = "";
		this.label = "";
		this.extra_link_target = "_self";
		this.extra_link_popup = "";
		this.expose_view = false;
	}

	connectedCallback()
	{
		super.connectedCallback();
		this._handleClick = this._handleClick.bind(this);
	}

	_imageTemplate()
	{
		let src = this.parse_href(this.src) || this.parse_href(this.default_src);
		if(!src)
		{
			// Hide if no valid image
			return '';
		}
		return html`
            <img ${this.id ? html`id="${this.id}"` : ''}
                 src="${src}"
                 alt="${this.label}"
                 title="${this.statustext || this.label}"
            >`;
	}

	render()
	{
		return html`
            <slot></slot>`;
	}


	protected parse_href(img_href : string) : string
	{
		// allow url's too
		if(img_href[0] == '/' || img_href.substr(0, 4) == 'http' || img_href.substr(0, 5) == 'data:')
		{
			return img_href;
		}
		let src = this.egw()?.image(img_href);
		if(src)
		{
			return src;
		}
		return "";
	}

	_handleClick(_ev : MouseEvent) : boolean
	{
		if(this.href)
		{
			this.egw().open_link(this.href, this.extra_link_target, this.extra_link_popup);
		}
		else
		{
			return super._handleClick(_ev);
		}
	}

	get _img()
	{
		return this.__getDirectSlotChild('img');
	}

	/**
	 * Handle changes that have to happen based on changes to properties
	 *
	 */
	updated(changedProperties)
	{
		super.updated(changedProperties);

		// if there's an href or onclick, make it look clickable
		if(changedProperties.has("href") || changedProperties.has("onclick"))
		{
			this.classList.toggle("et2_clickable", this.href || this.onclick)
		}
		for(const changedPropertiesKey in changedProperties)
		{
			if(Et2Image.getPropertyOptions()[changedPropertiesKey])
			{
				this._img[changedPropertiesKey] = this[changedPropertiesKey];
			}
		}
	}

	transformAttributes(_attrs : any)
	{
		super.transformAttributes(_attrs);

		// Expand src with additional stuff.  Copy & paste from legacy.
		if(typeof _attrs["src"] != "undefined")
		{
			let manager = this.getArrayMgr("content");
			if(manager && _attrs["src"])
			{
				let src = manager.getEntry(_attrs["src"], false, true);
				if(typeof src != "undefined" && src !== null)
				{
					if(typeof src == "object")
					{
						src = egw().link('/index.php', src);
					}
					this.src = src;
				}
			}
		}
	}

	/**
	 * Code for implementing et2_IDetachedDOM
	 *
	 * Individual widgets are detected and handled by the grid, but the interface is needed for this to happen
	 *
	 * @param {array} _attrs array to add further attributes to
	 */
	getDetachedAttributes(_attrs)
	{
		_attrs.push("src", "label", "href");
	}

	getDetachedNodes()
	{
		return [<HTMLElement><unknown>this];
	}

	setDetachedAttributes(_nodes, _values)
	{
		// Do nothing, setting attribute / property just sets it
	}
}

customElements.define("et2-image", Et2Image as any, {extends: 'img'});